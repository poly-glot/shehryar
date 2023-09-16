<?php
    require_once __DIR__ . '/inc/functions.php';

    $results = getResults("SELECT * FROM users");
    $results = array_map(function ($entry) {
        $entry['imageUri'] = $entry['img'];
        return $entry;
    }, $results);

    sendResponse(compact('results'));