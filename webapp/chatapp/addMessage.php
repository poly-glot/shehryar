<?php
require_once __DIR__ . '/inc/functions.php';

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $input = getUserInput();
    extract($input);

    if (!$sender_id || !$receiver_id || !$msg) {
        sendResponse(['error' => 'Some values are missing'], 400);
    }

    $connection = connect();
    $sender_id = mysqli_escape_string($connection, $sender_id);
    $receiver_id = mysqli_escape_string($connection, $receiver_id);
    $msg = mysqli_escape_string($connection, $msg);

    ensureContactExists($sender_id, $receiver_id);
    saveMessage($sender_id, $receiver_id, $msg);

    sendResponse(['success' => 'Successfully sent message']);
} else {
    sendResponse(['error' => 'What are you doing here'], 400);
}

function ensureContactExists($sender_id, $receiver_id) {
    $queryExists = "SELECT * 
    FROM contacts 
    WHERE 
      ( user1_ID = '{$sender_id}' AND user2_ID = '{$receiver_id}') 
      OR 
      ( user1_ID = '{$receiver_id}' AND user2_ID = '{$sender_id}' )";

    if (isRecordExists($queryExists)) {
        return false;
    }

    mysqli_query(connect(), "INSERT INTO contacts (user1_ID, user2_ID) VALUES ( '{$sender_id}', '{$receiver_id}') ");

    return true;
}

function saveMessage($sender_id, $receiver_id, $message) {
    $created_at = time();
    mysqli_query(connect(), "INSERT INTO messages (sender_id, receiver_id, msg, created_at) VALUES ( '{$sender_id}', '{$receiver_id}', '{$message}', '{$created_at}') ");
}
