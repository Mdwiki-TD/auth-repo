<?php
// src/logout.php

use OAuth\Controllers\LogoutController;

include_once __DIR__ . '/include_all.php';
include_once __DIR__ . '/app/controllers/LogoutController.php';

(new LogoutController())->handle();
