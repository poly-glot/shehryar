#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/chatapp/inc/config.php';

$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', Config::dbHost(), Config::dbName()),
    Config::dbUser(),
    Config::dbPass(),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

$lock = $pdo->query("SELECT GET_LOCK('shehryar_migrate', 60)")->fetchColumn();
if ($lock !== 1 && $lock !== '1') {
    fwrite(STDERR, "Could not acquire migration lock\n");
    exit(1);
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(255) PRIMARY KEY,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $files = glob(__DIR__ . '/../db/migrations/*.sql') ?: [];
    sort($files, SORT_STRING);

    $applied = array_flip(
        $pdo->query("SELECT version FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN)
    );

    $count = 0;
    foreach ($files as $path) {
        $version = basename($path, '.sql');
        if (isset($applied[$version])) {
            continue;
        }

        echo "→ {$version}\n";
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException("Failed to read {$path}");
        }

        // MySQL implicitly commits on DDL (CREATE/ALTER/DROP), so the
        // begin/commit pair is best-effort: it wraps pure DML migrations
        // atomically, but a DDL statement closes the transaction early.
        // We commit only if one is still open, and record the version
        // afterwards regardless.
        $pdo->beginTransaction();
        try {
            $pdo->exec($sql);
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
            $pdo->prepare("INSERT INTO schema_migrations (version) VALUES (?)")
                ->execute([$version]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $count++;
    }

    echo $count === 0 ? "Nothing to do.\n" : "Applied {$count} migration(s).\n";
} finally {
    $pdo->query("SELECT RELEASE_LOCK('shehryar_migrate')");
}
