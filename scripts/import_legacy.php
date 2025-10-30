<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/core/Database.php';

const LEGACY_USERS_PATH = __DIR__ . '/../json/proveedoresus.json';

function loadLegacyUsers(): array
{
    if (!is_file(LEGACY_USERS_PATH)) {
        throw new RuntimeException('No se localizó el archivo proveedoresus.json en la carpeta json/');
    }

    $payload = json_decode((string)file_get_contents(LEGACY_USERS_PATH), true);

    if (!is_array($payload) || empty($payload['proveedores']) || !is_array($payload['proveedores'])) {
        throw new RuntimeException('El archivo proveedoresus.json no contiene la estructura esperada.');
    }

    return $payload['proveedores'];
}

function parseLegacyDate(?string $value): ?string
{
    if (!$value) {
        return null;
    }

    try {
        $date = new DateTimeImmutable($value);
    } catch (Exception $exception) {
        return null;
    }

    return $date->format('Y-m-d H:i:s');
}

function ensureProvider(\PDO $pdo, array &$cache, int $externalId): int
{
    if ($externalId <= 0) {
        $externalId = 0;
    }

    if (array_key_exists($externalId, $cache)) {
        return $cache[$externalId];
    }

    $stmt = $pdo->prepare('SELECT id FROM proveedores.providers WHERE external_id = :external LIMIT 1');
    $stmt->execute(['external' => $externalId]);
    $existing = $stmt->fetchColumn();
    if ($existing !== false) {
        return $cache[$externalId] = (int)$existing;
    }

    $slug = $externalId === 0 ? 'prov_0' : 'prov_' . $externalId;
    $name = $externalId === 0 ? 'Proveedor Casa' : 'Proveedor ' . $externalId;

    $stmt = $pdo->prepare("
        INSERT INTO proveedores.providers (external_id, slug, name, status, activation_date, created_at, updated_at)
        VALUES (:external_id, :slug, :name, 'active', NOW()::date, NOW(), NOW())
        ON CONFLICT (external_id) DO UPDATE
        SET slug = EXCLUDED.slug,
            name = EXCLUDED.name,
            status = EXCLUDED.status,
            updated_at = NOW()
        RETURNING id
    ");
    $stmt->execute([
        'external_id' => $externalId,
        'slug' => $slug,
        'name' => $name,
    ]);

    $providerId = (int)$stmt->fetchColumn();
    if ($providerId <= 0) {
        throw new RuntimeException('No fue posible insertar/actualizar el proveedor con external_id ' . $externalId);
    }

    return $cache[$externalId] = $providerId;
}

function fetchRoleId(\PDO $pdo, string $code): int
{
    static $roles = [];

    if (isset($roles[$code])) {
        return $roles[$code];
    }

    $stmt = $pdo->prepare('SELECT id FROM proveedores.roles WHERE code = :code LIMIT 1');
    $stmt->execute(['code' => $code]);
    $roleId = $stmt->fetchColumn();
    if ($roleId === false) {
        throw new RuntimeException("El rol '{$code}' no existe. Ejecuta los seeds base antes del importador.");
    }

    return $roles[$code] = (int)$roleId;
}

function assignUserRole(\PDO $pdo, int $userId, int $roleId): void
{
    $pdo->prepare('DELETE FROM proveedores.user_roles WHERE user_id = :user')
        ->execute(['user' => $userId]);

    $pdo->prepare('INSERT INTO proveedores.user_roles (user_id, role_id) VALUES (:user, :role)')
        ->execute([
            'user' => $userId,
            'role' => $roleId,
        ]);
}

function main(): void
{
    $pdo = Database::pgsql();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $legacyUsers = loadLegacyUsers();
    $providerCache = [];
    $userMap = [];
    $userProviders = [];

    $pdo->beginTransaction();

    try {
        foreach ($legacyUsers as $entry) {
            $legacyId = (int)($entry['id'] ?? 0);
            $externalProvider = (int)($entry['id_proveedor'] ?? 0);
            $username = trim((string)($entry['user'] ?? ''));
            $passwordPlain = (string)($entry['password'] ?? '');

            if ($legacyId <= 0 || $username === '' || $passwordPlain === '') {
                continue;
            }

            $providerId = ensureProvider($pdo, $providerCache, $externalProvider);

            $allowedDays = array_values(array_filter(array_map('intval', array_map('trim', explode(',', (string)($entry['dias'] ?? ''))))));
            if (empty($allowedDays)) {
                $allowedDays = [1, 2, 3, 4, 5];
            }

            $createdAt = parseLegacyDate($entry['date'] ?? null) ?? date('Y-m-d H:i:s');
            $updatedAt = parseLegacyDate($entry['update_date'] ?? null) ?? $createdAt;

            $isActive = ((int)($entry['activo'] ?? 0)) === 1;
            $isCollapsed = ((int)($entry['collapse'] ?? 0)) === 1;
            $maxUsers = (int)($entry['catidad_user'] ?? 5);

            $passwordHash = password_hash($passwordPlain, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("
                INSERT INTO proveedores.users (provider_id, username, email, password_plain, password_hash, is_active, allowed_days, max_child_users, is_collapsed, created_at, updated_at)
                VALUES (:provider_id, :username, :email, :password_plain, :password_hash, :is_active, :allowed_days, :max_child_users, :is_collapsed, :created_at, :updated_at)
                ON CONFLICT (username) DO UPDATE
                SET provider_id = EXCLUDED.provider_id,
                    password_plain = EXCLUDED.password_plain,
                    password_hash = EXCLUDED.password_hash,
                    is_active = EXCLUDED.is_active,
                    allowed_days = EXCLUDED.allowed_days,
                    max_child_users = EXCLUDED.max_child_users,
                    is_collapsed = EXCLUDED.is_collapsed,
                    updated_at = EXCLUDED.updated_at
                RETURNING id
            ");
            $stmt->execute([
                'provider_id' => $providerId,
                'username' => $username,
                'email' => null,
                'password_plain' => $passwordPlain,
                'password_hash' => $passwordHash,
                'is_active' => $isActive ? 1 : 0,
                'allowed_days' => '{' . implode(',', $allowedDays) . '}',
                'max_child_users' => $maxUsers > 0 ? $maxUsers : 5,
                'is_collapsed' => $isCollapsed ? 1 : 0,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ]);

            $userId = (int)$stmt->fetchColumn();
            if ($userId <= 0) {
                throw new RuntimeException("No fue posible insertar el usuario {$username}");
            }

            $userMap[$legacyId] = $userId;

            $assignment = 'provider_user';
            if ((int)($entry['superadmin'] ?? 0) === 1) {
                $assignment = 'super_admin';
            } elseif ((int)($entry['admin'] ?? 0) === 1) {
                $assignment = 'provider_admin';
            }
            assignUserRole($pdo, $userId, fetchRoleId($pdo, $assignment));

            $providersList = [];
            if (!in_array($externalProvider, $providersList, true)) {
                $providersList[] = $externalProvider;
            }
            if (!empty($entry['proveedores'])) {
                foreach (explode(',', (string)$entry['proveedores']) as $raw) {
                    $providersList[] = (int)trim($raw);
                }
            }
            $userProviders[$userId] = $providersList;
        }

        // Asignar jerarquías
        foreach ($legacyUsers as $entry) {
            $legacyId = (int)($entry['id'] ?? 0);
            $parentLegacy = (int)($entry['parent_id'] ?? 0);

            if ($legacyId <= 0 || $parentLegacy <= 0) {
                continue;
            }

            if (isset($userMap[$legacyId], $userMap[$parentLegacy])) {
                $pdo->prepare('UPDATE proveedores.users SET parent_user_id = :parent WHERE id = :id')
                    ->execute([
                        'parent' => $userMap[$parentLegacy],
                        'id' => $userMap[$legacyId],
                    ]);
            }
        }

        // Insertar proveedores vinculados
        $deleteUp = $pdo->prepare('DELETE FROM proveedores.user_providers WHERE user_id = :user');
        $insertUp = $pdo->prepare('INSERT INTO proveedores.user_providers (user_id, provider_id) VALUES (:user, :provider) ON CONFLICT DO NOTHING');

        foreach ($userProviders as $userId => $providerIds) {
            $deleteUp->execute(['user' => $userId]);

            foreach ($providerIds as $externalId) {
                $providerDbId = ensureProvider($pdo, $providerCache, (int)$externalId);
                $insertUp->execute([
                    'user' => $userId,
                    'provider' => $providerDbId,
                ]);
            }
        }

        $pdo->commit();

        printf("Importación completa. Usuarios procesados: %d\n", count($userMap));
    } catch (Throwable $exception) {
        $pdo->rollBack();
        fwrite(STDERR, 'Error durante la importación: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}

main();
