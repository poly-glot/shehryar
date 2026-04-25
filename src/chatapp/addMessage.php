<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';

Http::requirePost();

[
    'sender_id'   => $senderId,
    'receiver_id' => $receiverId,
    'msg'         => $message,
] = Http::require(
    Http::jsonBody(),
    ['sender_id', 'receiver_id', 'msg'],
);

$pdo = Database::pdo();
$pdo->beginTransaction();

try {
    $contact = Database::first(
        'SELECT contactID FROM contacts
         WHERE (user1_ID = :a1 AND user2_ID = :b1)
            OR (user1_ID = :b2 AND user2_ID = :a2)
         LIMIT 1',
        ['a1' => $senderId, 'b1' => $receiverId, 'a2' => $senderId, 'b2' => $receiverId],
    );

    if ($contact === null) {
        Database::execute(
            'INSERT INTO contacts (user1_ID, user2_ID) VALUES (:sender, :receiver)',
            ['sender' => $senderId, 'receiver' => $receiverId],
        );
    }

    Database::execute(
        'INSERT INTO messages (sender_id, receiver_id, msg, created_at)
         VALUES (:sender, :receiver, :msg, :created_at)',
        [
            'sender'     => $senderId,
            'receiver'   => $receiverId,
            'msg'        => $message,
            'created_at' => time(),
        ],
    );

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

Http::json(['success' => 'Successfully sent message']);
