<?php
require_once __DIR__ . '/inc/functions.php';

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $input = getUserInput();
    extract($input);

    if (!$first_user_id || !$second_user_id) {
        sendResponse(['error' => 'Some values are missing'], 400);
    }

    $connection = connect();
    $first_user_id = mysqli_escape_string($connection, $first_user_id);
    $second_user_id = mysqli_escape_string($connection, $second_user_id);

    $results = getResults("SELECT * FROM messages WHERE ( sender_id = '{$first_user_id}' AND receiver_id = '{$second_user_id}') OR ( sender_id = '{$second_user_id}' AND receiver_id = '{$first_user_id}' ) ORDER BY msg_id DESC");
    $results = array_map(function ($row) {
        return [
            "id" => $row["msg_id"],
            "message" => $row["msg"],
            "sender" => $row["sender_id"],
            "receiver" => $row["receiver_id"],
            "sent_at" => $row["created_at"]
        ];
    }, $results);

    sendResponse(compact('results'));
} else {
    sendResponse(['error' => 'What are you doing here'], 400);
}

