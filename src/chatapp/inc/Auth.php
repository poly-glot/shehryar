<?php
declare(strict_types=1);

final class Auth
{
    public static function hash(#[\SensitiveParameter] string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    public static function verify(
        #[\SensitiveParameter] string $password,
        #[\SensitiveParameter] string $stored,
    ): bool {
        // Legacy rows may contain plaintext passwords. If the stored value is
        // not a recognizable password_hash() string, fall back to a constant-
        // time comparison so existing users can still sign in; callers should
        // rehash on successful login.
        if (!str_starts_with($stored, '$')) {
            return hash_equals($stored, $password);
        }
        return password_verify($password, $stored);
    }

    public static function needsRehash(#[\SensitiveParameter] string $stored): bool
    {
        return !str_starts_with($stored, '$')
            || password_needs_rehash($stored, PASSWORD_ARGON2ID);
    }

    public static function generateUniqueId(): string
    {
        // unique_id column is INT (UNIQUE) in the legacy schema; keep the
        // existing 10-digit numeric shape rather than widening to a hex string.
        return (string) random_int(1_000_000_000, 2_147_483_647);
    }
}
