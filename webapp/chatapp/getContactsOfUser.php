<?php
require_once __DIR__ . '/inc/functions.php';

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $input = getUserInput();
    extract($input);

    if (!$unique_id) {
        sendResponse(['error' => 'Some values are missing'], 400);
    }

    $connection = connect();
    $unique_id = mysqli_escape_string($connection, $unique_id);

    $query = "SELECT u.* 
FROM contacts c 
INNER JOIN users u 
WHERE 
(c.user1_ID = '$unique_id' OR c.user2_ID = '$unique_id')
AND
(u.unique_id = c.user1_ID OR u.unique_id = c.user2_ID)
GROUP BY u.user_id
ORDER BY c.contactID DESC
";

    $results = getResults($query);
    $results = array_map(function ($entry) {
        $entry['imageUri'] = $entry['img'];
        return $entry;
    }, $results);

    // Filter out / Remove provided unique_id
    $results = array_filter($results, function($entry) use ($unique_id) {
        return $entry['unique_id'] != $unique_id;
    });

    sendResponse(compact('results'));
} else {
    sendResponse(['error' => 'What are you doing here'], 400);
}
