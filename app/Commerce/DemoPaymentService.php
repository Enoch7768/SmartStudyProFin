<?php

declare(strict_types=1);

namespace App\Commerce;

use App\Core\Config;
use PDO;
use RuntimeException;
use Throwable;

final class DemoPaymentService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function canConfigure(): bool
    {
        return Config::get('payments.demo_allowed', false) === true
            && in_array(Config::get('app.environment'), ['local', 'development', 'staging'], true);
    }

    public function enabled(): bool
    {
        if (!$this->canConfigure()) return false;
        return (int) $this->pdo->query('SELECT enabled FROM payment_demo_settings WHERE id = 1')->fetchColumn() === 1;
    }

    public function setEnabled(bool $enabled, int $adminId): void
    {
        if (!$this->canConfigure()) throw new RuntimeException('Demo payments can only be configured in an explicitly allowed non-production environment.');
        $statement = $this->pdo->prepare('UPDATE payment_demo_settings SET enabled = :enabled, updated_by = :updated_by, updated_at = :updated_at WHERE id = 1');
        $statement->execute(['enabled' => $enabled ? 1 : 0, 'updated_by' => $adminId, 'updated_at' => gmdate('Y-m-d H:i:s')]);
        if ($statement->rowCount() !== 1) throw new RuntimeException('Demo payment settings could not be saved.');
    }

    public function simulate(int $orderId, int $userId, string $scenario): array
    {
        if (!$this->enabled()) throw new RuntimeException('Demo payments are not enabled.');
        if (!in_array($scenario, ['success', 'failed', 'cancelled'], true)) throw new RuntimeException('Choose a valid demo payment outcome.');
        $this->pdo->beginTransaction();
        try {
            $query = $this->pdo->prepare('SELECT * FROM orders WHERE id = :id AND user_id = :user_id');
            $query->execute(['id' => $orderId, 'user_id' => $userId]);
            $order = $query->fetch();
            if (!$order || $order['status'] !== 'pending') throw new RuntimeException('This order is not available for a demo payment.');
            $now = gmdate('Y-m-d H:i:s');
            $reference = 'DEMO-' . strtoupper(bin2hex(random_bytes(8)));
            $event = $this->pdo->prepare('INSERT INTO demo_payment_events (order_id, user_id, scenario, reference, created_at) VALUES (:order_id, :user_id, :scenario, :reference, :created_at)');
            $event->execute(['order_id' => $orderId, 'user_id' => $userId, 'scenario' => $scenario, 'reference' => $reference, 'created_at' => $now]);
            if ($scenario === 'success') {
                $update = $this->pdo->prepare("UPDATE orders SET status = 'paid', paid_at = :paid_at, updated_at = :updated_at WHERE id = :id AND user_id = :user_id AND status = 'pending'");
                $update->execute(['paid_at' => $now, 'updated_at' => $now, 'id' => $orderId, 'user_id' => $userId]);
                if ($update->rowCount() !== 1) throw new RuntimeException('The demo order could not be completed.');
                $lines = $this->pdo->prepare('SELECT item_type, item_id FROM order_items WHERE order_id = :order_id');
                $lines->execute(['order_id' => $orderId]);
                $enrol = $this->pdo->prepare("INSERT INTO course_enrolments (user_id, course_id, status, source, enrolled_at, updated_at) VALUES (:user_id, :course_id, 'active', 'payment', :enrolled_at, :updated_at) ON CONFLICT(user_id, course_id) DO UPDATE SET status = CASE WHEN course_enrolments.status = 'revoked' THEN 'active' ELSE course_enrolments.status END, source = 'payment', updated_at = excluded.updated_at");
                foreach ($lines->fetchAll() as $line) {
                    if ($line['item_type'] === 'course') $enrol->execute(['user_id' => $userId, 'course_id' => (int) $line['item_id'], 'enrolled_at' => $now, 'updated_at' => $now]);
                }
            } elseif ($scenario === 'cancelled') {
                $this->pdo->prepare("UPDATE orders SET status = 'cancelled', updated_at = :updated_at WHERE id = :id AND user_id = :user_id AND status = 'pending'")->execute(['updated_at' => $now, 'id' => $orderId, 'user_id' => $userId]);
            }
            $this->pdo->commit();
            return ['scenario' => $scenario, 'reference' => $reference, 'order_id' => $orderId];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }
}
