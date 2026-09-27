<?php
// src/callback.php

use OAuth\Controllers\CallbackController;

include_once __DIR__ . '/include_all.php';

(new CallbackController())->handle();
