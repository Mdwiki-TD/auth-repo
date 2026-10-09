<?php

declare (strict_types = 1);

namespace OAuth\Tests;

use OAuth\Settings;
use OAuth\User\CurrentUser;
use PHPUnit\Framework\TestCase;

class CurrentUserTest extends TestCase
{
    private CurrentUser $currentUser;

    protected function setUp(): void
    {
        $settings          = Settings::getInstance();
        $this->currentUser = CurrentUser::getInstance($settings);
    }

    public function testGetInstanceReturnsSameInstance(): void
    {
        $instance1 = CurrentUser::getInstance();
        $instance2 = CurrentUser::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function testInitialLoginStatus(): void
    {
        // Initially no user cookie set
        $this->assertSame('', $this->currentUser->getUsername());
        $this->assertFalse($this->currentUser->isLoggedIn());
        $this->assertNull($this->currentUser->getAlertMessage());
    }

    public function testLogout(): void
    {
        $this->currentUser->addUsernameToCookies('logout_test_user');
        $this->currentUser->destroy();

        $this->assertEmpty($_SESSION);
    }

    public function testEnsureSessionStarted(): void
    {
        CurrentUser::ensureSessionStarted();
        $this->assertNotEquals(PHP_SESSION_NONE, session_status());
    }
}
