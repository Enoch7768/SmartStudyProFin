<?php

declare(strict_types=1);

namespace App\Commerce;

use PDO;
use RuntimeException;
use Throwable;

final class RefundService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function request(int $orderItemId, int $adminId, int $amount, string $reason): int
    {
        $reason = trim($reason);
        if ($orderItemId < 1 || $adminId < 1 || $amount < 1 || $reason === '' || strlen($reason) > 500) {
            throw new RuntimeException('Choose an eligible order item and enter a valid refund amount and reason.');
        }

        $this->pdo->exec('BEGIN IMMEDIATE');
        try {
            $query = $this->pdo->prepare("SELECT oi.*, o.id AS order_id, o.status AS order_status, o.user_id, p.id AS payment_id, p.amount_ugx AS payment_amount FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id INNER JOIN payments p ON p.order_id = o.id AND p.status = 'paid' WHERE oi.id = :item_id ORDER BY p.id DESC LIMIT 1");
            $query->execute(['item_id' => $orderItemId]);
            $item = $query->fetch();
            if (!$item || !in_array($item['order_status'], ['paid', 'partially_refunded'], true)) {
                throw new RuntimeException('Only items in verified paid orders can be refunded.');
            }
            if (!($item['item_type'] === 'course' || ($item['item_type'] === 'product' && $item['product_type'] === 'online'))) {
                throw new RuntimeException('Refunds are limited to courses and digital products. Physical products and delivery are excluded.');
            }

            $reserved = $this->pdo->prepare("SELECT COALESCE(SUM(ri.amount_ugx), 0) FROM refund_items ri INNER JOIN refunds r ON r.id = ri.refund_id WHERE ri.order_item_id = :item_id AND r.status IN ('pending', 'succeeded')");
            $reserved->execute(['item_id' => $orderItemId]);
            $remaining = (int) $item['line_total_ugx'] - (int) $reserved->fetchColumn();
            if ($amount > $remaining) {
                throw new RuntimeException('The refund exceeds this item’s remaining refundable balance.');
            }

            $now = gmdate('Y-m-d H:i:s');
            $insert = $this->pdo->prepare("INSERT INTO refunds (payment_id, amount_ugx, reason, status, created_at, requested_by, updated_at) VALUES (:payment_id, :amount, :reason, 'pending', :created_at, :requested_by, :updated_at)");
            $insert->execute(['payment_id' => (int) $item['payment_id'], 'amount' => $amount, 'reason' => $reason, 'created_at' => $now, 'requested_by' => $adminId, 'updated_at' => $now]);
            $refundId = (int) $this->pdo->lastInsertId();
            $allocation = $this->pdo->prepare('INSERT INTO refund_items (refund_id, order_item_id, amount_ugx, created_at) VALUES (:refund_id, :item_id, :amount, :created_at)');
            $allocation->execute(['refund_id' => $refundId, 'item_id' => $orderItemId, 'amount' => $amount, 'created_at' => $now]);
            $this->audit($refundId, $adminId, 'requested', $amount, $reason, '', $now);
            $this->pdo->commit();
            return $refundId;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function resolve(int $refundId, int $adminId, string $status, string $reference, string $note): void
    {
        $reference = trim($reference);
        $note = trim($note);
        if ($refundId < 1 || $adminId < 1 || !in_array($status, ['succeeded', 'failed'], true) || strlen($reference) > 190 || $note === '' || strlen($note) > 500) {
            throw new RuntimeException('Enter a valid refund resolution.');
        }
        if ($status === 'succeeded' && $reference === '') {
            throw new RuntimeException('Enter the provider refund reference after the refund is confirmed by the provider.');
        }

        $this->pdo->exec('BEGIN IMMEDIATE');
        try {
            $query = $this->pdo->prepare("SELECT r.*, p.id AS payment_id, p.amount_ugx AS payment_amount, o.id AS order_id, o.user_id, o.total_ugx AS order_total FROM refunds r INNER JOIN payments p ON p.id = r.payment_id INNER JOIN orders o ON o.id = p.order_id WHERE r.id = :id");
            $query->execute(['id' => $refundId]);
            $refund = $query->fetch();
            if (!$refund || $refund['status'] !== 'pending') {
                throw new RuntimeException('This refund is no longer awaiting resolution.');
            }
            $now = gmdate('Y-m-d H:i:s');
            $update = $this->pdo->prepare("UPDATE refunds SET status = :status, provider_refund_id = :reference, resolved_by = :resolved_by, resolution_reference = :resolution, resolved_at = :resolved_at, updated_at = :updated_at WHERE id = :id AND status = 'pending'");
            $update->execute(['status' => $status, 'reference' => $status === 'succeeded' ? $reference : null, 'resolved_by' => $adminId, 'resolution' => $note, 'resolved_at' => $now, 'updated_at' => $now, 'id' => $refundId]);
            if ($update->rowCount() !== 1) throw new RuntimeException('The refund was already resolved.');
            $this->audit($refundId, $adminId, $status, (int) $refund['amount_ugx'], $note, $reference, $now);

            if ($status === 'succeeded') {
                $sum = $this->pdo->prepare("SELECT COALESCE(SUM(amount_ugx), 0) FROM refunds WHERE payment_id = :payment_id AND status = 'succeeded'");
                $sum->execute(['payment_id' => (int) $refund['payment_id']]);
                $refunded = (int) $sum->fetchColumn();
                if ($refunded > (int) $refund['payment_amount']) {
                    throw new RuntimeException('Recorded refunds exceed the captured payment amount.');
                }

                $paymentStatus = $refunded >= (int) $refund['payment_amount'] ? 'refunded' : 'paid';
                $orderStatus = $refunded >= (int) $refund['order_total'] ? 'refunded' : 'partially_refunded';
                $this->pdo->prepare('UPDATE payments SET status = :status, updated_at = :updated_at WHERE id = :id')->execute(['status' => $paymentStatus, 'updated_at' => $now, 'id' => (int) $refund['payment_id']]);
                $this->pdo->prepare('UPDATE orders SET status = :status, updated_at = :updated_at WHERE id = :id')->execute(['status' => $orderStatus, 'updated_at' => $now, 'id' => (int) $refund['order_id']]);

                $allocations = $this->pdo->prepare('SELECT ri.order_item_id, oi.item_type, oi.item_id, oi.line_total_ugx FROM refund_items ri INNER JOIN order_items oi ON oi.id = ri.order_item_id WHERE ri.refund_id = :refund_id');
                $allocations->execute(['refund_id' => $refundId]);
                $lineRefunded = $this->pdo->prepare("SELECT COALESCE(SUM(ri.amount_ugx), 0) FROM refund_items ri INNER JOIN refunds r ON r.id = ri.refund_id WHERE ri.order_item_id = :item_id AND r.status = 'succeeded'");
                $otherPurchase = $this->pdo->prepare("SELECT 1 FROM orders o INNER JOIN payments p ON p.order_id = o.id AND p.status = 'paid' INNER JOIN order_items oi ON oi.order_id = o.id AND oi.item_type = 'course' WHERE o.user_id = :user_id AND oi.item_id = :course_id AND oi.id != :item_id AND o.status IN ('paid', 'partially_refunded') AND COALESCE((SELECT SUM(ri.amount_ugx) FROM refund_items ri INNER JOIN refunds r ON r.id = ri.refund_id WHERE ri.order_item_id = oi.id AND r.status = 'succeeded'), 0) < oi.line_total_ugx LIMIT 1");
                $revoke = $this->pdo->prepare("UPDATE course_enrolments SET status = 'revoked', updated_at = :updated_at WHERE user_id = :user_id AND course_id = :course_id AND source = 'payment' AND status IN ('active', 'completed')");

                foreach ($allocations->fetchAll() as $allocation) {
                    $lineRefunded->execute(['item_id' => (int) $allocation['order_item_id']]);
                    if ((int) $lineRefunded->fetchColumn() < (int) $allocation['line_total_ugx']) continue;
                    if ($allocation['item_type'] === 'course') {
                        $otherPurchase->execute(['user_id' => (int) $refund['user_id'], 'course_id' => (int) $allocation['item_id'], 'item_id' => (int) $allocation['order_item_id']]);
                        if (!$otherPurchase->fetchColumn()) {
                            $revoke->execute(['updated_at' => $now, 'user_id' => (int) $refund['user_id'], 'course_id' => (int) $allocation['item_id']]);
                        }
                    }
                }
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function all(): array
    {
        return $this->pdo->query('SELECT r.*, p.provider, p.provider_transaction_id, o.public_id, o.customer_name, o.customer_email, actor.name AS requested_by_name, resolver.name AS resolved_by_name, oi.item_title AS refunded_item_title FROM refunds r INNER JOIN payments p ON p.id = r.payment_id INNER JOIN orders o ON o.id = p.order_id LEFT JOIN refund_items ri ON ri.refund_id = r.id LEFT JOIN order_items oi ON oi.id = ri.order_item_id LEFT JOIN users actor ON actor.id = r.requested_by LEFT JOIN users resolver ON resolver.id = r.resolved_by ORDER BY r.id DESC LIMIT 300')->fetchAll();
    }

    public function auditTrail(int $refundId): array
    {
        $query = $this->pdo->prepare('SELECT e.*, u.name AS actor_name FROM refund_audit_events e INNER JOIN users u ON u.id = e.actor_id WHERE e.refund_id = :refund_id ORDER BY e.id');
        $query->execute(['refund_id' => $refundId]);
        return $query->fetchAll();
    }

    private function audit(int $refundId, int $actorId, string $action, int $amount, string $reason, string $reference, string $createdAt): void
    {
        $statement = $this->pdo->prepare('INSERT INTO refund_audit_events (refund_id, actor_id, action, amount_ugx, reason, reference, created_at) VALUES (:refund_id, :actor_id, :action, :amount, :reason, :reference, :created_at)');
        $statement->execute(['refund_id' => $refundId, 'actor_id' => $actorId, 'action' => $action, 'amount' => $amount, 'reason' => $reason, 'reference' => $reference, 'created_at' => $createdAt]);
    }
}
