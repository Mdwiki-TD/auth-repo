<?php
if (isset($_REQUEST['test'])) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
};

// if not $_GET
if (empty($_GET)) {
    include_once __DIR__ . '/view.php';
} else {
    require __DIR__ . '/auth/index.php';
}
