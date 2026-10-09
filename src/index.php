<?php
// src/index.php

include_once __DIR__ . '/bootstrap.php';

$ye = count($_GET) == 1 && isset($_GET['test']);

if (empty($_GET) || $ye) {
    exit();
}

$allowedActions = ['login', 'callback', 'logout'];
$action = $_GET['a'] ?? '';

if (in_array($action, $allowedActions)) {

    $actionFile = $action . '.php';

    include_once __DIR__ . "/" . $actionFile;
};
