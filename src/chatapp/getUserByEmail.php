<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';

Http::requirePost();

['email' => $email] = Http::require(Http::jsonBody(), ['email']);

$user = Database::first(
    'SELECT user_id, unique_id, fname, lname, email, img, status
     FROM users WHERE email = :email LIMIT 1',
    ['email' => $email],
);

if ($user === null) {
    Http::json(['error' => 'User not found'], 404);
}

$user['imageUri'] = $user['img'];

Http::json(['results' => $user]);
