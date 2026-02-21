<?php
if (isset($_REQUEST['test'])) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
};

// length of $_GET == 1 and isset($_GET['test'])
$ye = count($_GET) == 1 && isset($_GET['test']);

// if not $_GET or only $_GET['test'] isset

if (empty($_GET) || $ye ) {
    include_once __DIR__ . '/view.php';
} else {
    require __DIR__ . '/oauth/index.php';
}
