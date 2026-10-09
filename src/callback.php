<?php
// src/callback.php

use OAuth\Controllers\CallbackController;

include_once __DIR__ . '/bootstrap.php';

(new CallbackController())->handle();
