<?php
declare(strict_types=1);

final class Config
{
    public static function dbHost(): string { return self::required('DB_HOST'); }
    public static function dbUser(): string { return self::required('DB_USER'); }
    public static function dbPass(): string { return self::required('DB_PASS'); }
    public static function dbName(): string { return self::required('DB_NAME'); }

    public static function isProd(): bool
    {
        return (getenv('APP_ENV') ?: 'production') === 'production';
    }

    private static function required(string $key): string
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            throw new RuntimeException("Missing required environment variable: {$key}");
        }
        return $value;
    }
}
