<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';

Http::requirePost();

[
    'first_user_id'  => $firstUserId,
    'second_user_id' => $secondUserId,
] = Http::require(
    Http::jsonBody(),
    ['first_user_id', 'second_user_id'],
);

$rows = Database::all(
    'SELECT msg_id, msg, sender_id, receiver_id, created_at
     FROM messages
     WHERE (sender_id = :a1 AND receiver_id = :b1)
        OR (sender_id = :b2 AND receiver_id = :a2)
     ORDER BY msg_id ASC',
    ['a1' => $firstUserId, 'b1' => $secondUserId, 'a2' => $firstUserId, 'b2' => $secondUserId],
);

$results = array_map(static fn (array $row): array => [
    'id'       => $row['msg_id'],
    'message'  => $row['msg'],
    'sender'   => $row['sender_id'],
    'receiver' => $row['receiver_id'],
    'sent_at'  => $row['created_at'],
], $rows);

Http::json(['results' => $results]);
