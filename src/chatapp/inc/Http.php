<?php
declare(strict_types=1);

final class Http
{
    public static function requirePost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            self::json(['error' => 'Method not allowed'], 405);
        }
    }

    /** @return array<string,mixed> */
    public static function jsonBody(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        if ($raw === '') {
            return [];
        }
        try {
            $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            self::json(['error' => 'Invalid JSON body'], 400);
        }
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string,mixed> $body
     * @param list<string>        $required
     * @return array<string,mixed>
     */
    public static function require(array $body, array $required): array
    {
        $out = [];
        foreach ($required as $key) {
            $value = $body[$key] ?? null;
            if ($value === null || $value === '') {
                self::json(['error' => "Missing required field: {$key}"], 400);
            }
            $out[$key] = $value;
        }
        return $out;
    }

    public static function json(mixed $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
