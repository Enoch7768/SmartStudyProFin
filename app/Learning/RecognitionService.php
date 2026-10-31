<?php

declare(strict_types=1);

namespace App\Learning;

use PDO;
use RuntimeException;

final class RecognitionService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function sync(int $userId): void
    {
        $stats = $this->stats($userId);
        $catalog = $this->pdo->query('SELECT id, slug, name, description, threshold, metric FROM achievements ORDER BY id')->fetchAll();
        $now = gmdate('Y-m-d H:i:s');
        foreach ($catalog as $achievement) {
            if ((int) ($stats[$achievement['metric']] ?? 0) < (int) $achievement['threshold']) continue;
            $insert = $this->pdo->prepare('INSERT OR IGNORE INTO user_achievements (user_id, achievement_id, earned_at) VALUES (:user_id, :achievement_id, :earned_at)');
            $insert->execute(['user_id' => $userId, 'achievement_id' => (int) $achievement['id'], 'earned_at' => $now]);
            if ($insert->rowCount() === 1) {
                $this->notify($userId, 'achievement:' . $achievement['slug'], 'achievement', 'Achievement unlocked: ' . $achievement['name'], $achievement['description'], 'achievements.php');
            }
        }
        $completed = $this->pdo->prepare("SELECT e.course_id, e.completed_at, c.title FROM course_enrolments e INNER JOIN cms_courses c ON c.id = e.course_id WHERE e.user_id = :user_id AND e.status = 'completed' ORDER BY e.completed_at, e.course_id");
        $completed->execute(['user_id' => $userId]);
        foreach ($completed->fetchAll() as $course) {
            $existing = $this->pdo->prepare('SELECT id FROM certificates WHERE user_id = :user_id AND course_id = :course_id');
            $existing->execute(['user_id' => $userId, 'course_id' => (int) $course['course_id']]);
            if ($existing->fetchColumn()) continue;
            $code = strtoupper(bin2hex(random_bytes(12)));
            $issue = $this->pdo->prepare('INSERT OR IGNORE INTO certificates (user_id, course_id, verification_code, issued_at) VALUES (:user_id, :course_id, :code, :issued_at)');
            $issue->execute(['user_id' => $userId, 'course_id' => (int) $course['course_id'], 'code' => $code, 'issued_at' => $course['completed_at'] ?: $now]);
            if ($issue->rowCount() === 1) {
                $this->notify($userId, 'certificate:' . (int) $course['course_id'], 'certificate', 'Your certificate is ready', 'You completed ' . $course['title'] . '. Your certificate is ready to view and verify.', 'certificates.php');
            }
        }
    }

    public function notifications(int $userId, int $limit = 30): array
    {
        $limit = max(1, min($limit, 100));
        $statement = $this->pdo->prepare('SELECT id, type, title, message, target_url, created_at, read_at FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC, id DESC LIMIT ' . $limit);
        $statement->execute(['user_id' => $userId]);
        return $statement->fetchAll();
    }

    public function unreadCount(int $userId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND read_at IS NULL');
        $statement->execute(['user_id' => $userId]);
        return (int) $statement->fetchColumn();
    }

    public function markRead(int $userId, int $notificationId): bool
    {
        $statement = $this->pdo->prepare('UPDATE notifications SET read_at = COALESCE(read_at, :read_at) WHERE id = :id AND user_id = :user_id');
        $statement->execute(['read_at' => gmdate('Y-m-d H:i:s'), 'id' => $notificationId, 'user_id' => $userId]);
        return $statement->rowCount() > 0;
    }

    public function markAllRead(int $userId): void
    {
        $this->pdo->prepare('UPDATE notifications SET read_at = COALESCE(read_at, :read_at) WHERE user_id = :user_id')->execute(['read_at' => gmdate('Y-m-d H:i:s'), 'user_id' => $userId]);
    }

    public function achievements(int $userId): array
    {
        $statement = $this->pdo->prepare('SELECT a.slug, a.name, a.description, a.icon, ua.earned_at FROM achievements a LEFT JOIN user_achievements ua ON ua.achievement_id = a.id AND ua.user_id = :user_id ORDER BY CASE WHEN ua.earned_at IS NULL THEN 1 ELSE 0 END, ua.earned_at DESC, a.id');
        $statement->execute(['user_id' => $userId]);
        return $statement->fetchAll();
    }

    public function certificates(int $userId): array
    {
        $statement = $this->pdo->prepare('SELECT cert.verification_code, cert.issued_at, c.title AS course_title FROM certificates cert INNER JOIN cms_courses c ON c.id = cert.course_id WHERE cert.user_id = :user_id ORDER BY cert.issued_at DESC, cert.id DESC');
        $statement->execute(['user_id' => $userId]);
        return $statement->fetchAll();
    }

    public function verifyCertificate(string $code): ?array
    {
        $code = strtoupper(trim($code));
        if (!preg_match('/^[A-F0-9]{24}$/', $code)) return null;
        $statement = $this->pdo->prepare("SELECT cert.verification_code, cert.issued_at, c.title AS course_title, u.name AS learner_name FROM certificates cert INNER JOIN cms_courses c ON c.id = cert.course_id INNER JOIN users u ON u.id = cert.user_id WHERE cert.verification_code = :code LIMIT 1");
        $statement->execute(['code' => $code]);
        return $statement->fetch() ?: null;
    }

    public function analytics(): array
    {
        $summary = $this->pdo->query("SELECT (SELECT COUNT(*) FROM users u INNER JOIN user_roles ur ON ur.user_id = u.id INNER JOIN roles r ON r.id = ur.role_id WHERE r.slug = 'student' AND u.status = 'active') AS students, (SELECT COUNT(*) FROM course_enrolments WHERE status IN ('active', 'completed')) AS enrolments, (SELECT COUNT(*) FROM course_enrolments WHERE status = 'completed') AS completions, (SELECT COUNT(*) FROM certificates) AS certificates, (SELECT COUNT(*) FROM quiz_attempts) AS quiz_attempts, (SELECT COALESCE(ROUND(AVG(score_percent), 1), 0) FROM quiz_attempts) AS average_score, (SELECT COALESCE(SUM(total_ugx), 0) FROM orders WHERE status = 'paid') AS revenue_ugx")->fetch();
        $courses = $this->pdo->query("SELECT c.id, c.title, COUNT(DISTINCT e.user_id) AS enrolled, COUNT(DISTINCT CASE WHEN e.status = 'completed' THEN e.user_id END) AS completed FROM cms_courses c LEFT JOIN course_enrolments e ON e.course_id = c.id WHERE c.status = 'published' GROUP BY c.id, c.title ORDER BY enrolled DESC, c.title LIMIT 10")->fetchAll();
        $monthly = $this->pdo->query("SELECT strftime('%Y-%m', enrolled_at) AS month, COUNT(*) AS enrolments FROM course_enrolments WHERE enrolled_at >= datetime('now', '-11 months') GROUP BY strftime('%Y-%m', enrolled_at) ORDER BY month")->fetchAll();
        $recent = $this->pdo->query("SELECT u.name, c.title AS course_title, e.completed_at FROM course_enrolments e INNER JOIN users u ON u.id = e.user_id INNER JOIN cms_courses c ON c.id = e.course_id WHERE e.status = 'completed' ORDER BY e.completed_at DESC LIMIT 10")->fetchAll();
        return ['summary' => $summary, 'courses' => $courses, 'monthly' => $monthly, 'recent_completions' => $recent];
    }

    private function stats(int $userId): array
    {
        $statement = $this->pdo->prepare("SELECT (SELECT COUNT(*) FROM lesson_progress WHERE user_id = :lessons_user AND status = 'completed') AS lessons_completed, (SELECT COUNT(*) FROM quiz_attempts WHERE user_id = :quizzes_user AND passed = 1) AS quizzes_passed, (SELECT COUNT(*) FROM course_enrolments WHERE user_id = :courses_user AND status = 'completed') AS courses_completed");
        $statement->execute(['lessons_user' => $userId, 'quizzes_user' => $userId, 'courses_user' => $userId]);
        return $statement->fetch() ?: [];
    }

    private function notify(int $userId, string $eventKey, string $type, string $title, string $message, string $url): void
    {
        $statement = $this->pdo->prepare('INSERT OR IGNORE INTO notifications (user_id, event_key, type, title, message, target_url, created_at) VALUES (:user_id, :event_key, :type, :title, :message, :target_url, :created_at)');
        $statement->execute(['user_id' => $userId, 'event_key' => $eventKey, 'type' => $type, 'title' => $title, 'message' => $message, 'target_url' => $url, 'created_at' => gmdate('Y-m-d H:i:s')]);
    }
}
