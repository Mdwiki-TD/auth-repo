<?php

declare(strict_types=1);

namespace OAuth\Tests;

use OAuth\Controllers\LoginController;
use OAuth\Controllers\LogoutController;
use OAuth\Controllers\CallbackController;
use PHPUnit\Framework\TestCase;

class ControllersTest extends TestCase
{
    public function testLoginControllerCanBeInstantiated(): void
    {
        $controller = new LoginController();
        $this->assertInstanceOf(LoginController::class, $controller);
    }

    public function testLogoutControllerCanBeInstantiated(): void
    {
        $controller = new LogoutController();
        $this->assertInstanceOf(LogoutController::class, $controller);
    }

    public function testCallbackControllerCanBeInstantiated(): void
    {
        $controller = new CallbackController();
        $this->assertInstanceOf(CallbackController::class, $controller);
    }
}
