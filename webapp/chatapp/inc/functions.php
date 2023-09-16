<?php
require_once __DIR__ . '/config.php';

$dbConnection = null;

function connect() {
    global $dbConnection;

    if ($dbConnection) {
        return $dbConnection;
    }

    $dbConnection = mysqli_connect(DB_HOST, DB_USER,DB_PASS);
    mysqli_select_db($dbConnection,DB_NAME);

    return $dbConnection;
}

function disconnect() {
    global $dbConnection;

    mysqli_close($dbConnection);
}

function getUserInput() {
    return json_decode(file_get_contents("php://input"),true);
}

function sendResponse($content, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");

    echo json_encode($content);
    exit;
}

function isRecordExists($sql) {
    $connection = connect();
    $query = mysqli_query($connection, $sql);

    return mysqli_num_rows($query) > 1;
}

function getResults($query) {
    $result_set = mysqli_query(connect(), $query);

    if ($result_set) {
        $rows = [];

        while ($result = mysqli_fetch_array($result_set, MYSQLI_ASSOC)) {
            $rows[] = $result;
        }

        return $rows;
    }

    // Report Error
    sendResponse([
        "error" => mysqli_error(connect()),
    ], 500);
}