<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use App\Auth\Authentication;
use App\Database\Database;

$auth = new Authentication(Database::getInstance());
$auth->logout();
header('Location: /');
return; // use Exception\throw new Exception('Error message') # use Exception