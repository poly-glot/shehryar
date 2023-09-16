<?php
require_once __DIR__ . '/inc/functions.php';

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $input = getUserInput();
    extract($input);

    if (!$Fname || !$Lname || !$Email || !$Password || !$Image) {
        sendResponse(['error' => 'All input fields are required!'], 400);
    }

    $results = getResults("SELECT * FROM users WHERE email = '{$Email}'");
    if (count($results) > 0) {
        sendResponse(['error' => "$Email - This email already Exists!"], 400);
    }

    $random_id = rand(time(), 1000000);
    $Status = "Active Now";

    $connection = connect();
    $Fname = mysqli_escape_string($connection, $Fname);
    $Lname = mysqli_escape_string($connection, $Lname);
    $Email = mysqli_escape_string($connection, $Email);
    $Password = mysqli_escape_string($connection, $Password);
    $Image = mysqli_escape_string($connection, $Image);

    $insertRecord = mysqli_query($connection, "INSERT INTO users (unique_id, fname, lname, email, password, img, status) VALUES ( '{$random_id}' , '{$Fname}', '{$Lname}', '{$Email}', '{$Password}', '{$Image}', '{$Status}')");

    if ($insertRecord) {
        $_SESSION['unique_id'] = $random_id;
        sendResponse(['success' => true]);
    } else {
        sendResponse(['error' => 'Something went wrong'], 400);
    }

} else {
    sendResponse(['error' => 'What are you doing here'], 400);
}
