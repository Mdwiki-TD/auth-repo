<?php
// src/logout.php

use OAuth\Controllers\LogoutController;

include_once __DIR__ . '/bootstrap.php';

(new LogoutController())->handle();
