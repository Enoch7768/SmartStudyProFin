<?php

declare(strict_types=1);

namespace App\Learning;

use App\Core\Session;
use PDO;
use RuntimeException;

final class LearningService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function courses(int $userId): array
    {
        $statement = $this->pdo->prepare("SELECT c.id, c.slug, c.title, c.description_html, c.price_ugx, c.category, c.status, e.status AS enrolment_status, (SELECT COUNT(*) FROM cms_chapters ch WHERE ch.course_id = c.id) AS chapter_count, (SELECT COUNT(*) FROM cms_lessons l INNER JOIN cms_chapters ch ON ch.id = l.chapter_id WHERE ch.course_id = c.id AND l.status = 'published') AS lesson_count, (SELECT COUNT(*) FROM lesson_progress lp WHERE lp.user_id = :progress_user AND lp.course_id = c.id AND lp.status = 'completed') AS completed_lessons FROM cms_courses c LEFT JOIN course_enrolments e ON e.course_id = c.id AND e.user_id = :enrol_user WHERE c.status = 'published' ORDER BY CASE WHEN e.status IN ('active', 'completed') THEN 0 ELSE 1 END, c.sort_order, c.id");
        $statement->execute(['progress_user' => $userId, 'enrol_user' => $userId]);
        return $statement->fetchAll();
    }

    public function course(int $courseId): ?array
    {
        $statement = $this->pdo->prepare("SELECT * FROM cms_courses WHERE id = :id AND status = 'published' LIMIT 1");
        $statement->execute(['id' => $courseId]);
        return $statement->fetch() ?: null;
    }

    public function enrolFreeCourse(int $userId, int $courseId): void
    {
        $course = $this->course($courseId);
        if (!$course) throw new RuntimeException('This course is not available.');
        if ((int) $course['price_ugx'] > 0) throw new RuntimeException('This course requires a confirmed purchase. Payment and enrolment activation are handled in the secure checkout workflow.');
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare("INSERT INTO course_enrolments (user_id, course_id, status, source, enrolled_at, updated_at) VALUES (:user_id, :course_id, 'active', 'free', :enrolled_at, :updated_at) ON CONFLICT(user_id, course_id) DO UPDATE SET status = CASE WHEN course_enrolments.status = 'revoked' THEN 'active' ELSE course_enrolments.status END, updated_at = excluded.updated_at");
        $statement->execute(['user_id' => $userId, 'course_id' => $courseId, 'enrolled_at' => $now, 'updated_at' => $now]);
    }

    public function hasAccess(int $userId, int $courseId): bool
    {
        $statement = $this->pdo->prepare("SELECT 1 FROM course_enrolments e INNER JOIN cms_courses c ON c.id = e.course_id WHERE e.user_id = :user_id AND e.course_id = :course_id AND e.status IN ('active', 'completed') AND c.status = 'published' AND m.visibility = 'private' LIMIT 1");
        $statement->execute(['user_id' => $userId, 'course_id' => $courseId]);
        return (bool) $statement->fetchColumn();
    }

    public function outline(int $userId, int $courseId): array
    {
        if (!$this->hasAccess($userId, $courseId)) throw new RuntimeException('You are not enrolled in this course.');
        $statement = $this->pdo->prepare("SELECT ch.id AS chapter_id, ch.title AS chapter_title, l.id AS lesson_id, l.title AS lesson_title, l.status AS lesson_status, l.video_media_id, l.video_url, l.resource_media_id, l.resource_title, COALESCE(lp.status, 'not_started') AS progress_status, COALESCE(lp.last_position_seconds, 0) AS last_position_seconds, CASE WHEN b.id IS NULL THEN 0 ELSE 1 END AS bookmarked FROM cms_chapters ch LEFT JOIN cms_lessons l ON l.chapter_id = ch.id AND l.status = 'published' LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.user_id = :progress_user LEFT JOIN lesson_bookmarks b ON b.lesson_id = l.id AND b.user_id = :bookmark_user WHERE ch.course_id = :course_id ORDER BY ch.sort_order, ch.id, l.sort_order, l.id");
        $statement->execute(['progress_user' => $userId, 'bookmark_user' => $userId, 'course_id' => $courseId]);
        $chapters = [];
        foreach ($statement->fetchAll() as $row) {
            $chapterId = (int) $row['chapter_id'];
            if (!isset($chapters[$chapterId])) $chapters[$chapterId] = ['id' => $chapterId, 'title' => $row['chapter_title'], 'lessons' => []];
            if ($row['lesson_id'] !== null) $chapters[$chapterId]['lessons'][] = [
                'id' => (int) $row['lesson_id'],
                'title' => $row['lesson_title'],
                'progress_status' => $row['progress_status'],
                'last_position_seconds' => (int) $row['last_position_seconds'],
                'bookmarked' => (bool) $row['bookmarked'],
                'video_media_id' => $row['video_media_id'] === null ? null : (int) $row['video_media_id'],
                'resource_media_id' => $row['resource_media_id'] === null ? null : (int) $row['resource_media_id'],
                'resource_title' => $row['resource_title'],
                'video_url' => $row['video_url']
            ];
        }
        return array_values($chapters);
    }

    public function lesson(int $userId, int $courseId, int $lessonId): ?array
    {
        if (!$this->hasAccess($userId, $courseId)) return null;
        $statement = $this->pdo->prepare("SELECT l.*, ch.course_id FROM cms_lessons l INNER JOIN cms_chapters ch ON ch.id = l.chapter_id WHERE l.id = :lesson_id AND ch.course_id = :course_id AND l.status = 'published' LIMIT 1");
        $statement->execute(['lesson_id' => $lessonId, 'course_id' => $courseId]);
        $lesson = $statement->fetch();
        return $lesson ?: null;
    }

    public function startLesson(int $userId, int $courseId, int $lessonId): void
    {
        if (!$this->lesson($userId, $courseId, $lessonId)) throw new RuntimeException('This lesson is not available in your course.');
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare("INSERT INTO lesson_progress (user_id, course_id, lesson_id, status, started_at, updated_at) VALUES (:user_id, :course_id, :lesson_id, 'started', :started_at, :updated_at) ON CONFLICT(user_id, lesson_id) DO UPDATE SET updated_at = excluded.updated_at");
        $statement->execute(['user_id' => $userId, 'course_id' => $courseId, 'lesson_id' => $lessonId, 'started_at' => $now, 'updated_at' => $now]);
    }

    public function updatePosition(int $userId, int $courseId, int $lessonId, int $seconds): void
    {
        $this->startLesson($userId, $courseId, $lessonId);
        $statement = $this->pdo->prepare('UPDATE lesson_progress SET last_position_seconds = :seconds, updated_at = :updated_at WHERE user_id = :user_id AND course_id = :course_id AND lesson_id = :lesson_id AND status != \'completed\'');
        $statement->execute(['seconds' => max(0, min($seconds, 86400)), 'updated_at' => gmdate('Y-m-d H:i:s'), 'user_id' => $userId, 'course_id' => $courseId, 'lesson_id' => $lessonId]);
    }

    public function completeLesson(int $userId, int $courseId, int $lessonId): void
    {
        $this->startLesson($userId, $courseId, $lessonId);
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare("UPDATE lesson_progress SET status = 'completed', completed_at = COALESCE(completed_at, :completed_at), updated_at = :updated_at WHERE user_id = :user_id AND course_id = :course_id AND lesson_id = :lesson_id");
        $statement->execute(['completed_at' => $now, 'updated_at' => $now, 'user_id' => $userId, 'course_id' => $courseId, 'lesson_id' => $lessonId]);
        $totals = $this->pdo->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN lp.status = 'completed' THEN 1 ELSE 0 END) AS completed FROM cms_lessons l INNER JOIN cms_chapters ch ON ch.id = l.chapter_id LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.user_id = :user_id WHERE ch.course_id = :course_id AND l.status = 'published'");
        $totals->execute(['user_id' => $userId, 'course_id' => $courseId]);
        $counts = $totals->fetch();
        if ((int) $counts['total'] > 0 && (int) $counts['completed'] >= (int) $counts['total']) {
            $this->pdo->prepare("UPDATE course_enrolments SET status = 'completed', completed_at = COALESCE(completed_at, :completed_at), updated_at = :updated_at WHERE user_id = :user_id AND course_id = :course_id AND status = 'active'")->execute(['completed_at' => $now, 'updated_at' => $now, 'user_id' => $userId, 'course_id' => $courseId]);
        }
    }

    public function toggleBookmark(int $userId, int $courseId, int $lessonId): bool
    {
        if (!$this->lesson($userId, $courseId, $lessonId)) throw new RuntimeException('This lesson is not available.');
        $find = $this->pdo->prepare('SELECT id FROM lesson_bookmarks WHERE user_id = :user_id AND lesson_id = :lesson_id');
        $find->execute(['user_id' => $userId, 'lesson_id' => $lessonId]);
        if ($find->fetchColumn()) {
            $this->pdo->prepare('DELETE FROM lesson_bookmarks WHERE user_id = :user_id AND lesson_id = :lesson_id')->execute(['user_id' => $userId, 'lesson_id' => $lessonId]);
            return false;
        }
        $this->pdo->prepare('INSERT INTO lesson_bookmarks (user_id, lesson_id, created_at) VALUES (:user_id, :lesson_id, :created_at)')->execute(['user_id' => $userId, 'lesson_id' => $lessonId, 'created_at' => gmdate('Y-m-d H:i:s')]);
        return true;
    }

    public function submitQuiz(int $userId, int $courseId, int $lessonId, array $answers): array
    {
        if (!$this->lesson($userId, $courseId, $lessonId)) throw new RuntimeException('This lesson is not available.');
        $lesson = $this->lesson($userId, $courseId, $lessonId);
        if (!$lesson) throw new RuntimeException('This lesson is not available.');
        $timeLimit = (int) ($lesson['quiz_time_limit_minutes'] ?? 0);
        $passMark = (int) ($lesson['quiz_pass_mark'] ?? 70);
        Session::start();
        $timerKey = 'quiz_started_' . $lessonId;
        if (!isset($_SESSION[$timerKey])) throw new RuntimeException('Your quiz session expired. Reload the lesson to start a new attempt.');
        $startedAt = (int) $_SESSION[$timerKey];
        if ($timeLimit > 0 && time() - $startedAt > ($timeLimit * 60) + 10) {
            unset($_SESSION[$timerKey]);
            throw new RuntimeException('Your quiz time has expired. Reload the lesson to begin a new attempt.');
        }
        $statement = $this->pdo->prepare('SELECT id, question, options_json, correct_answer FROM cms_quiz_questions WHERE lesson_id = :lesson_id ORDER BY sort_order, id');
        $statement->execute(['lesson_id' => $lessonId]);
        $questions = $statement->fetchAll();
        if (!$questions) throw new RuntimeException('There are no quiz questions for this lesson yet.');
        $correct = 0;
        $review = [];
        $storedAnswers = [];
        foreach ($questions as $question) {
            $id = (int) $question['id'];
            $options = json_decode((string) $question['options_json'], true);
            if (!is_array($options)) $options = [];
            $answer = trim((string) ($answers[(string) $id] ?? ''));
            if (!in_array($answer, $options, true)) $answer = '';
            $isCorrect = $answer !== '' && hash_equals((string) $question['correct_answer'], $answer);
            if ($isCorrect) $correct++;
            $storedAnswers[(string) $id] = $answer;
            $review[] = ['question' => $question['question'], 'selected' => $answer, 'correct_answer' => $question['correct_answer'], 'is_correct' => $isCorrect];
        }
        $total = count($questions);
        $score = (int) round(($correct / $total) * 100);
        $now = gmdate('Y-m-d H:i:s');
        $insert = $this->pdo->prepare('INSERT INTO quiz_attempts (user_id, course_id, lesson_id, answers_json, question_count, correct_count, score_percent, passed, started_at, submitted_at) VALUES (:user_id, :course_id, :lesson_id, :answers_json, :question_count, :correct_count, :score_percent, :passed, :started_at, :submitted_at)');
        $insert->execute(['user_id' => $userId, 'course_id' => $courseId, 'lesson_id' => $lessonId, 'answers_json' => json_encode($storedAnswers, JSON_THROW_ON_ERROR), 'question_count' => $total, 'correct_count' => $correct, 'score_percent' => $score, 'passed' => $score >= $passMark ? 1 : 0, 'started_at' => gmdate('Y-m-d H:i:s', $startedAt), 'submitted_at' => $now]);
        unset($_SESSION[$timerKey]);
        return ['attempt_id' => (int) $this->pdo->lastInsertId(), 'total' => $total, 'correct' => $correct, 'score' => $score, 'passed' => $score >= $passMark, 'pass_mark' => $passMark, 'review' => $review];
    }

    public function quizQuestions(int $userId, int $courseId, int $lessonId): array
    {
        if (!$this->lesson($userId, $courseId, $lessonId)) throw new RuntimeException('This lesson is not available.');
        $lesson = $this->lesson($userId, $courseId, $lessonId);
        if (!$lesson) throw new RuntimeException('This lesson is not available.');
        Session::start();
        $timerKey = 'quiz_started_' . $lessonId;
        if (!isset($_SESSION[$timerKey])) $_SESSION[$timerKey] = time();
        $statement = $this->pdo->prepare('SELECT id, question, options_json FROM cms_quiz_questions WHERE lesson_id = :lesson_id ORDER BY sort_order, id');
        $statement->execute(['lesson_id' => $lessonId]);
        $questions = array_map(static function (array $row): array {
            $options = json_decode((string) $row['options_json'], true);
            return ['id' => (int) $row['id'], 'question' => $row['question'], 'options' => is_array($options) ? $options : []];
        }, $statement->fetchAll());
        return ['questions' => $questions, 'time_limit_minutes' => (int) $lesson['quiz_time_limit_minutes'], 'pass_mark' => (int) $lesson['quiz_pass_mark'], 'started_at' => (int) $_SESSION[$timerKey]];
    }

    public function dashboard(int $userId): array
    {
        $courses = $this->courses($userId);
        $enrolled = array_values(array_filter($courses, static fn (array $course): bool => in_array($course['enrolment_status'], ['active', 'completed'], true)));
        $recent = $this->pdo->prepare("SELECT lp.course_id, lp.lesson_id, l.title AS lesson_title, c.title AS course_title, lp.updated_at FROM lesson_progress lp INNER JOIN cms_lessons l ON l.id = lp.lesson_id INNER JOIN cms_courses c ON c.id = lp.course_id WHERE lp.user_id = :user_id AND lp.status = 'started' ORDER BY lp.updated_at DESC LIMIT 8");
        $recent->execute(['user_id' => $userId]);
        $bookmarks = $this->pdo->prepare('SELECT b.lesson_id, l.title AS lesson_title, ch.course_id, c.title AS course_title FROM lesson_bookmarks b INNER JOIN cms_lessons l ON l.id = b.lesson_id INNER JOIN cms_chapters ch ON ch.id = l.chapter_id INNER JOIN cms_courses c ON c.id = ch.course_id WHERE b.user_id = :user_id AND c.status = 'published' AND EXISTS (SELECT 1 FROM course_enrolments e WHERE e.user_id = b.user_id AND e.course_id = ch.course_id AND e.status IN ('active', 'completed')) ORDER BY b.created_at DESC LIMIT 8');
        $bookmarks->execute(['user_id' => $userId]);
        $attempts = $this->pdo->prepare('SELECT qa.id, qa.score_percent, qa.passed, qa.submitted_at, l.title AS lesson_title, c.title AS course_title FROM quiz_attempts qa INNER JOIN cms_lessons l ON l.id = qa.lesson_id INNER JOIN cms_courses c ON c.id = qa.course_id WHERE qa.user_id = :user_id ORDER BY qa.submitted_at DESC LIMIT 8');
        $attempts->execute(['user_id' => $userId]);
        return ['courses' => $courses, 'enrolled' => $enrolled, 'recent' => $recent->fetchAll(), 'bookmarks' => $bookmarks->fetchAll(), 'attempts' => $attempts->fetchAll()];
    }

    public function media(int $userId, int $mediaId): ?array
    {
        $statement = $this->pdo->prepare("SELECT m.*, ch.course_id FROM media_assets m INNER JOIN cms_lessons l ON (l.video_media_id = m.id OR l.resource_media_id = m.id) INNER JOIN cms_chapters ch ON ch.id = l.chapter_id INNER JOIN course_enrolments e ON e.course_id = ch.course_id INNER JOIN cms_courses c ON c.id = ch.course_id WHERE m.id = :media_id AND e.user_id = :user_id AND e.status IN ('active', 'completed') AND l.status = 'published' AND c.status = 'published' LIMIT 1");
        $statement->execute(['media_id' => $mediaId, 'user_id' => $userId]);
        return $statement->fetch() ?: null;
    }

    public function attempts(int $userId, int $lessonId): array
    {
        $statement = $this->pdo->prepare('SELECT id, question_count, correct_count, score_percent, passed, submitted_at FROM quiz_attempts WHERE user_id = :user_id AND lesson_id = :lesson_id ORDER BY submitted_at DESC LIMIT 20');
        $statement->execute(['user_id' => $userId, 'lesson_id' => $lessonId]);
        return $statement->fetchAll();
    }
}
