<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';

Http::requirePost();

['Email' => $email, 'Password' => $password] = Http::require(
    Http::jsonBody(),
    ['Email', 'Password'],
);

$user = Database::first(
    'SELECT unique_id, fname, lname, email, password, img FROM users WHERE email = :email LIMIT 1',
    ['email' => $email],
);

if ($user === null || !Auth::verify($password, $user['password'])) {
    Http::json(['error' => 'Email or Password is incorrect!'], 401);
}

if (Auth::needsRehash($user['password'])) {
    Database::execute(
        'UPDATE users SET password = :password WHERE unique_id = :id',
        ['password' => Auth::hash($password), 'id' => $user['unique_id']],
    );
}

Http::json([
    'results' => [
        'Message'   => 'success',
        'UniqueID'  => $user['unique_id'],
        'FirstName' => $user['fname'],
        'LastName'  => $user['lname'],
        'Email'     => $user['email'],
        'Image'     => $user['img'],
    ],
]);
