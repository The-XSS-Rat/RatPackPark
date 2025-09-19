<?php
// Utility functions to manage temporary tenant lifecycle

if (!function_exists('ensureTemporaryTenantSchema')) {
    function ensureTemporaryTenantSchema(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS temporary_tenants (" .
            "id INT AUTO_INCREMENT PRIMARY KEY," .
            "session_id VARCHAR(255) NOT NULL," .
            "account_id INT NOT NULL," .
            "account_name VARCHAR(150) NOT NULL," .
            "admin_username VARCHAR(100) NOT NULL," .
            "admin_password VARCHAR(100) NOT NULL," .
            "manager_username VARCHAR(100) NOT NULL," .
            "manager_password VARCHAR(100) NOT NULL," .
            "operator_username VARCHAR(100) NOT NULL," .
            "operator_password VARCHAR(100) NOT NULL," .
            "seller_username VARCHAR(100) NOT NULL," .
            "seller_password VARCHAR(100) NOT NULL," .
            "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP," .
            "expires_at DATETIME NOT NULL," .
            "destroyed_at DATETIME DEFAULT NULL," .
            "KEY idx_session_destroyed (session_id, destroyed_at)" .
            ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    }
}

if (!function_exists('rat_track_store_tenant_meta')) {
    function rat_track_store_tenant_meta(array $tenant): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($tenant['id'])) {
            $_SESSION['temporary_tenant_id'] = (int)$tenant['id'];
        }
        if (isset($tenant['account_id'])) {
            $_SESSION['temporary_tenant_account_id'] = (int)$tenant['account_id'];
        }

        if (!empty($tenant['created_at'])) {
            try {
                $createdAt = new DateTime($tenant['created_at'], new DateTimeZone('UTC'));
                $_SESSION['temporary_tenant_started_at'] = $createdAt->getTimestamp();
            } catch (Throwable $e) {
                // Leave the timer unset if we fail to parse the timestamp
            }
        }
    }
}

if (!function_exists('cleanupExpiredTemporaryTenants')) {
    function cleanupExpiredTemporaryTenants(PDO $pdo): void
    {
        ensureTemporaryTenantSchema($pdo);
        $stmt = $pdo->prepare("SELECT * FROM temporary_tenants WHERE destroyed_at IS NULL AND expires_at <= UTC_TIMESTAMP()");
        $stmt->execute();
        $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tenants as $tenant) {
            destroyTemporaryTenant($pdo, $tenant);
        }
    }
}

if (!function_exists('destroyTemporaryTenant')) {
    function destroyTemporaryTenant(PDO $pdo, array $tenant): void
    {
        $pdo->beginTransaction();
        try {
            $accountId = (int)$tenant['account_id'];

            // Remove related problem notes and reports before deleting the account to respect FK constraints.
            $problemIdsStmt = $pdo->prepare("SELECT id FROM problem_reports WHERE account_id = ?");
            $problemIdsStmt->execute([$accountId]);
            $problemIds = $problemIdsStmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($problemIds)) {
                $placeholders = implode(',', array_fill(0, count($problemIds), '?'));
                $notesDelete = $pdo->prepare("DELETE FROM problem_notes WHERE problem_id IN ($placeholders)");
                $notesDelete->execute($problemIds);
            }

            $problemDelete = $pdo->prepare("DELETE FROM problem_reports WHERE account_id = ?");
            $problemDelete->execute([$accountId]);

            // Deleting the account cascades to users, tickets, shifts, etc.
            $accountDelete = $pdo->prepare("DELETE FROM accounts WHERE id = ?");
            $accountDelete->execute([$accountId]);

            $markDestroyed = $pdo->prepare("UPDATE temporary_tenants SET destroyed_at = UTC_TIMESTAMP() WHERE id = ?");
            $markDestroyed->execute([(int)$tenant['id']]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('getActiveTemporaryTenant')) {
    function getActiveTemporaryTenant(PDO $pdo, string $sessionId): ?array
    {
        ensureTemporaryTenantSchema($pdo);
        $stmt = $pdo->prepare(
            "SELECT * FROM temporary_tenants WHERE session_id = ? AND destroyed_at IS NULL ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute([$sessionId]);
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($tenant === false) {
            return null;
        }

        $expiresAt = new DateTime($tenant['expires_at'], new DateTimeZone('UTC'));
        $now = new DateTime('now', new DateTimeZone('UTC'));
        if ($expiresAt <= $now) {
            destroyTemporaryTenant($pdo, $tenant);
            return null;
        }

        rat_track_store_tenant_meta($tenant);
        return $tenant;
    }
}

if (!function_exists('createTemporaryTenant')) {
    function createTemporaryTenant(PDO $pdo, string $sessionId): array
    {
        cleanupExpiredTemporaryTenants($pdo);
        $existing = getActiveTemporaryTenant($pdo, $sessionId);
        if ($existing !== null) {
            return $existing;
        }

        $pdo->beginTransaction();
        try {
            $accountName = 'Temp Tenant ' . strtoupper(bin2hex(random_bytes(3)));
            $insertAccount = $pdo->prepare("INSERT INTO accounts (name, contact_email) VALUES (?, ?)");
            $insertAccount->execute([$accountName, null]);
            $accountId = (int)$pdo->lastInsertId();

            $roles = [
                'admin' => 1,
                'manager' => 2,
                'seller' => 3,
                'operator' => 4,
            ];

            $credentialMap = [];
            foreach ($roles as $label => $roleId) {
                $username = buildUniqueUsername($pdo, $label);
                $passwordPlain = generateReadablePassword();
                $email = $username . "@example.com";
                $passwordHash = password_hash($passwordPlain, PASSWORD_BCRYPT);

                $insertUser = $pdo->prepare(
                    "INSERT INTO users (account_id, role_id, username, email, password_hash) VALUES (?, ?, ?, ?, ?)"
                );
                $insertUser->execute([$accountId, $roleId, $username, $email, $passwordHash]);

                $credentialMap[$label] = [
                    'username' => $username,
                    'password' => $passwordPlain,
                ];
            }

            $expiresAt = new DateTime('now', new DateTimeZone('UTC'));
            $expiresAt->modify('+12 hours');

            $insertTemp = $pdo->prepare(
                "INSERT INTO temporary_tenants (session_id, account_id, account_name, admin_username, admin_password, " .
                "manager_username, manager_password, operator_username, operator_password, seller_username, seller_password, expires_at) " .
                "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $insertTemp->execute([
                $sessionId,
                $accountId,
                $accountName,
                $credentialMap['admin']['username'],
                $credentialMap['admin']['password'],
                $credentialMap['manager']['username'],
                $credentialMap['manager']['password'],
                $credentialMap['operator']['username'],
                $credentialMap['operator']['password'],
                $credentialMap['seller']['username'],
                $credentialMap['seller']['password'],
                $expiresAt->format('Y-m-d H:i:s'),
            ]);

            $tempId = (int)$pdo->lastInsertId();
            $pdo->commit();

            $record = getActiveTemporaryTenant($pdo, $sessionId);
            if ($record === null) {
                throw new RuntimeException('Temporary tenant was created but could not be reloaded.');
            }

            rat_track_store_tenant_meta($record);
            return $record;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('buildUniqueUsername')) {
    function buildUniqueUsername(PDO $pdo, string $label): string
    {
        $base = 'temp_' . preg_replace('/[^a-z0-9]/', '', strtolower($label));
        do {
            $suffix = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
            $username = $base . '_' . $suffix;
            $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $check->execute([$username]);
        } while ((int)$check->fetchColumn() > 0);

        return $username;
    }
}

if (!function_exists('generateReadablePassword')) {
    function generateReadablePassword(int $length = 12): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^*';
        $maxIndex = strlen($alphabet) - 1;
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, $maxIndex)];
        }
        return $password;
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    require_once 'vendor/autoload.php';
    require_once 'db.php';

    session_start();

    ensureTemporaryTenantSchema($pdo);
    cleanupExpiredTemporaryTenants($pdo);

    $sessionId = session_id();
    $tenant = getActiveTemporaryTenant($pdo, $sessionId);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($tenant === null) {
            $tenant = createTemporaryTenant($pdo, $sessionId);
        }
    }

    if ($tenant !== null) {
        $_SESSION['temporary_tenant_id'] = $tenant['id'];
        header('Location: generate_tenant.php');
        exit;
    }

    header('Location: index.php');
    exit;
}
