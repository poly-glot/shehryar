<?php
ini_set('display_errors', 'on');
session_start();

define('DB_HOST', getenv('DB_HOST'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASS', base64_decode(getenv('DB_PASS')));
define('DB_NAME', getenv('DB_NAME'));
