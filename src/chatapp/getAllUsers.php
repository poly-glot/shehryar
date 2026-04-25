<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';

$rows = Database::all(
    'SELECT user_id, unique_id, fname, lname, email, img, status FROM users',
);

$results = array_map(static function (array $row): array {
    $row['imageUri'] = $row['img'];
    return $row;
}, $rows);

Http::json(['results' => $results]);
