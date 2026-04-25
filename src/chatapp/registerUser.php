<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';

Http::requirePost();

[
    'Fname'    => $fname,
    'Lname'    => $lname,
    'Email'    => $email,
    'Password' => $password,
    'Image'    => $image,
] = Http::require(
    Http::jsonBody(),
    ['Fname', 'Lname', 'Email', 'Password', 'Image'],
);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    Http::json(['error' => 'Invalid email address'], 400);
}

$existing = Database::first(
    'SELECT 1 FROM users WHERE email = :email LIMIT 1',
    ['email' => $email],
);
if ($existing !== null) {
    Http::json(['error' => "{$email} - This email already exists!"], 409);
}

$uniqueId = Auth::generateUniqueId();

Database::execute(
    'INSERT INTO users (unique_id, fname, lname, email, password, img, status)
     VALUES (:unique_id, :fname, :lname, :email, :password, :img, :status)',
    [
        'unique_id' => $uniqueId,
        'fname'     => $fname,
        'lname'     => $lname,
        'email'     => $email,
        'password'  => Auth::hash($password),
        'img'       => $image,
        'status'    => 'Active Now',
    ],
);

Http::json(['success' => true, 'unique_id' => $uniqueId], 201);
