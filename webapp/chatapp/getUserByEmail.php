<?php

require_once __DIR__ . '/inc/functions.php';

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $input = getUserInput();
    extract($input);

    if (!$email) {
        sendResponse(['error' => 'Some values are missing'], 400);
    }

    $results = getResults("SELECT * FROM users WHERE email = '{$email}'");
    $results = array_map(function ($entry) {
        $entry['imageUri'] = $entry['img'];
        return $entry;
    }, $results);

    $results = $results[0];

    sendResponse(compact('results'));
} else {
    sendResponse(['error' => 'What are you doing here'], 400);
}
