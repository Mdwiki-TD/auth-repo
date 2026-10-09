<?php

declare (strict_types = 1);

namespace OAuth\Tests;

use OAuth\Utils;
use PHPUnit\Framework\TestCase;

class UtilsTest extends TestCase
{
    public function testCreateStateReturnsEmptyArrayWhenNoGetParams(): void
    {
        $state = Utils::create_state(['camp', 'cat', 'code']);
        $this->assertIsArray($state);
    }

    public function testCreateReturnToEmptyReferer(): void
    {
        $this->assertEquals('', Utils::create_return_to(''));
        $this->assertEquals('', Utils::create_return_to(null));
    }

    public function testCreateReturnToValidDomains(): void
    {
        $valid1 = 'https://mdwiki.toolforge.org/Translation_Dashboard/index.php';
        $valid2 = 'http://localhost:8000/some/path';

        $this->assertEquals($valid1, Utils::create_return_to($valid1));
        $this->assertEquals($valid2, Utils::create_return_to($valid2));
    }

    public function testCreateReturnToDisallowedDomains(): void
    {
        $invalid1 = 'https://evil.com/phishing';
        $invalid2 = 'https://wikimedia.org/index.php';

        $this->assertEquals('', Utils::create_return_to($invalid1));
        $this->assertEquals('', Utils::create_return_to($invalid2));
    }

    public function testCreateReturnToRejectsAuthPaths(): void
    {
        $authUrl1 = 'https://mdwiki.toolforge.org/auth/login.php';
        $authUrl2 = 'http://localhost/auth/callback.php';

        $this->assertEquals('', Utils::create_return_to($authUrl1));
        $this->assertEquals('', Utils::create_return_to($authUrl2));
    }
}
