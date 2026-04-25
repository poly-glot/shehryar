<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        return self::$pdo ??= self::connect();
    }

    private static function connect(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            Config::dbHost(),
            Config::dbName(),
        );

        return new PDO($dsn, Config::dbUser(), Config::dbPass(), [
            PDO::ATTR_ERRMODE                       => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE            => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES              => false,
            PDO::ATTR_STRINGIFY_FETCHES             => false,
            PDO::MYSQL_ATTR_SSL_CA                  => '',
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT  => false,
        ]);
    }

    /** @param array<string,scalar|null> $params */
    public static function all(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @param array<string,scalar|null> $params */
    public static function first(string $sql, array $params = []): ?array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @param array<string,scalar|null> $params */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
}
