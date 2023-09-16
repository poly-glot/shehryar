<?php
require_once __DIR__ . '/inc/functions.php';

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $input = getUserInput();
    extract($input);

    if (!$Email || !$Password) {
        sendResponse(['error' => 'All input fields are required!'], 400);
    }

    $results = getResults("SELECT * FROM users WHERE email = '{$Email}' AND password = '{$Password}' ");
    $results = array_map(function ($row) {
        return [
            "Message" => "success",
            "UniqueID" => $row["unique_id"],
            "FirstName" => $row["fname"],
            "LastName" => $row["lname"],
            "Email" => $row["email"],
            "Password" => $row["password"],
            "Image" => $row["img"]
        ];
    }, $results);

    $results = $results[0];

    if (!$results) {
        sendResponse(['error' => 'Email or Password is incorrect!'], 400);
    }

    sendResponse(compact('results'));
} else {
   // sendResponse(['error' => 'What are you doing here'.$_SERVER['REQUEST_METHOD']], 400);
}
