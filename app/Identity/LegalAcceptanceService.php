<?php

declare(strict_types=1);

namespace App\Identity;

use PDO;
use RuntimeException;

final class LegalAcceptanceService
{
    public const VERSIONS = ['terms' => '1.0.0', 'privacy' => '1.0.0'];

    public function __construct(private PDO $pdo)
    {
    }

    public function hasCurrentAcceptance(int $userId): bool
    {
        if ($userId < 1) return false;
        $statement = $this->pdo->prepare('SELECT COUNT(DISTINCT document) FROM user_legal_acceptances WHERE user_id = :user_id AND ((document = :terms AND version = :terms_version) OR (document = :privacy AND version = :privacy_version))');
        $statement->execute(['user_id' => $userId, 'terms' => 'terms', 'terms_version' => self::VERSIONS['terms'], 'privacy' => 'privacy', 'privacy_version' => self::VERSIONS['privacy']]);
        return (int) $statement->fetchColumn() === count(self::VERSIONS);
    }

    public function acceptCurrent(int $userId, string $ip = '', string $userAgent = ''): void
    {
        if ($userId < 1) throw new RuntimeException('A signed-in account is required.');
        $now = gmdate('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare('INSERT INTO user_legal_acceptances (user_id, document, version, accepted_at, ip_hash, user_agent_hash) VALUES (:user_id, :document, :version, :accepted_at, :ip_hash, :user_agent_hash) ON CONFLICT(user_id, document, version) DO NOTHING');
            foreach (self::VERSIONS as $document => $version) {
                $statement->execute(['user_id' => $userId, 'document' => $document, 'version' => $version, 'accepted_at' => $now, 'ip_hash' => $ip === '' ? null : hash('sha256', $ip), 'user_agent_hash' => $userAgent === '' ? null : hash('sha256', $userAgent)]);
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }
}
