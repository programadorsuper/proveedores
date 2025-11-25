<?php

require_once __DIR__ . '/../core/Database.php';

class UserSessions
{
    protected \PDO $db;

    public function __construct()
    {
        $this->db = Database::pgsql();
    }

    public function createPersistent(int $userId, string $selectorHash, string $validatorHash, string $ip, string $userAgent, int $expiresAt): array
    {
        $sql = "
            INSERT INTO proveedores.user_sessions (
                user_id,
                selector_hash,
                validator_hash,
                ip_address,
                user_agent,
                expires_at
            ) VALUES (
                :user_id,
                :selector_hash,
                :validator_hash,
                :ip,
                :agent,
                TO_TIMESTAMP(:expires_at)
            )
            RETURNING id, expires_at
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'selector_hash' => $selectorHash,
            'validator_hash' => $validatorHash,
            'ip' => $ip,
            'agent' => substr($userAgent, 0, 500),
            'expires_at' => $expiresAt,
        ]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: [];
    }

    public function findActiveBySelectorHash(string $selectorHash): ?array
    {
        $sql = "
            SELECT *
            FROM proveedores.user_sessions
            WHERE selector_hash = :selector_hash
            ORDER BY id DESC
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['selector_hash' => $selectorHash]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        if (!empty($row['is_revoked'])) {
            return null;
        }

        if (!empty($row['expires_at'])) {
            $expiresAt = strtotime((string)$row['expires_at']);
            if ($expiresAt !== false && $expiresAt < time()) {
                return null;
            }
        }

        return $row;
    }

    public function touch(int $sessionId): void
    {
        $sql = "
            UPDATE proveedores.user_sessions
            SET last_seen_at = NOW(), updated_at = NOW()
            WHERE id = :id
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $sessionId]);
    }

    public function revoke(int $sessionId): void
    {
        $sql = "
            UPDATE proveedores.user_sessions
            SET is_revoked = TRUE, updated_at = NOW()
            WHERE id = :id
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $sessionId]);
    }

    public function revokeBySelectorHash(string $selectorHash): void
    {
        $sql = "
            UPDATE proveedores.user_sessions
            SET is_revoked = TRUE, updated_at = NOW()
            WHERE selector_hash = :selector_hash
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['selector_hash' => $selectorHash]);
    }

    public function purgeExpired(): void
    {
        $sql = "
            UPDATE proveedores.user_sessions
            SET is_revoked = TRUE, updated_at = NOW()
            WHERE is_revoked = FALSE
              AND expires_at < NOW()
        ";
        $this->db->exec($sql);
    }
}
