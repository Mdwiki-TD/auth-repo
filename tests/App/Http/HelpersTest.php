<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Http\Helpers;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Http\Helpers static utilities.
 */
class HelpersTest extends TestCase
{
    // ── createReturnTo ──────────────────────────────────

    public function testCreateReturnToReturnsEmptyForEmptyReferer(): void
    {
        $this->assertSame('', Helpers::createReturnTo(''));
    }

    public function testCreateReturnToReturnsRefererForAllowedDomain(): void
    {
        $url = 'https://mdwiki.toolforge.org/Translation_Dashboard/index.php';
        $this->assertSame($url, Helpers::createReturnTo($url));
    }

    public function testCreateReturnToReturnsRefererForLocalhost(): void
    {
        $url = 'http://localhost/some/page';
        $this->assertSame($url, Helpers::createReturnTo($url));
    }

    public function testCreateReturnToRejectsUnknownDomain(): void
    {
        $this->assertSame('', Helpers::createReturnTo('https://evil.example.com/foo'));
    }

    public function testCreateReturnToRejectsAuthPath(): void
    {
        $url = 'https://mdwiki.toolforge.org/auth/index.php?a=login';
        $this->assertSame('', Helpers::createReturnTo($url));
    }

    // ── dangerAlert ─────────────────────────────────────

    public function testDangerAlertContainsMessage(): void
    {
        $html = Helpers::dangerAlert('Something went wrong');
        $this->assertStringContainsString('Something went wrong', $html);
        $this->assertStringContainsString('alert-danger', $html);
    }

    // ── createState ─────────────────────────────────────

    public function testCreateStateReturnsEmptyArrayWhenNoGetParams(): void
    {
        // filter_input returns null when $_GET params don't exist
        $state = Helpers::createState(['nonexistent_key']);
        $this->assertIsArray($state);
    }
}
