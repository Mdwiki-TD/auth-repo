<?php

declare(strict_types=1);

namespace OAuth\Tests;

use OAuth\Settings;
use Defuse\Crypto\Key;
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase
{
    private Settings $settings;

    protected function setUp(): void
    {
        $this->settings = Settings::getInstance();
    }

    public function testGetInstanceReturnsSameInstance(): void
    {
        $instance1 = Settings::getInstance();
        $instance2 = Settings::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function testEnvironmentChecks(): void
    {
        $this->assertTrue($this->settings->isTesting());
        $this->assertFalse($this->settings->isDevelopment());
        $this->assertFalse($this->settings->isProduction());
    }

    public function testGenerateCallbackUrl(): void
    {
        $url = $this->settings->generateCallbackUrl('/auth/callback.php');
        $this->assertStringStartsWith('http://', $url);
        $this->assertStringEndsWith('/auth/callback.php', $url);

        $urlCustom = $this->settings->generateCallbackUrl('custom/path.php');
        $this->assertStringEndsWith('/custom/path.php', $urlCustom);
    }

    public function testGetKey(): void
    {
        $cookieKey = $this->settings->getKey('cookie');
        $cryptKey = $this->settings->getKey('crypt');

        $this->assertInstanceOf(Key::class, $cookieKey);
        $this->assertInstanceOf(Key::class, $cryptKey);
    }

    public function testEncodeAndDecodeValue(): void
    {
        $key = $this->settings->getKey('cookie');
        $original = 'sensitive_data_123';

        $encoded = $this->settings->encodeValue($original, $key);
        $this->assertNotEmpty($encoded);
        $this->assertNotEquals($original, $encoded);

        $decoded = $this->settings->decodeValue($encoded, $key);
        $this->assertEquals($original, $decoded);
    }

    public function testEncodeAndDecodeWithNullKeyOrEmptyString(): void
    {
        $this->assertEquals('', $this->settings->encodeValue('', null));
        $this->assertEquals('', $this->settings->encodeValue('test', null));
        $this->assertEquals('', $this->settings->decodeValue('', null));
        $this->assertEquals('', $this->settings->decodeValue('test', null));
        $this->assertEquals('', $this->settings->decodeValue('invalid_ciphertext', $this->settings->getKey('cookie')));
    }

    public function testSpecialCharactersEncryption(): void
    {
        $key = $this->settings->getKey('cookie');
        $specialStrings = [
            'test@example.com',
            'user+name',
            'test value with spaces',
            'unicode: مرحبا',
            'symbols: !@#$%^&*()',
        ];

        foreach ($specialStrings as $original) {
            $encoded = $this->settings->encodeValue($original, $key);
            $decoded = $this->settings->decodeValue($encoded, $key);
            $this->assertEquals($original, $decoded, "Failed to encode/decode: {$original}");
        }
    }

    public function testPropertyGetAndSetters(): void
    {
        $this->assertEquals('test_consumer_key', $this->settings->consumerKey);
        $this->assertEquals('test_consumer_secret', $this->settings->consumerSecret);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Settings are read-only');
        /** @phpstan-ignore-next-line */
        $this->settings->someNewSetting = 'new_value';
    }

    public function testUndefinedPropertyAccessThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Undefined setting');
        /** @phpstan-ignore-next-line */
        $_ = $this->settings->nonExistentProperty;
    }
}
