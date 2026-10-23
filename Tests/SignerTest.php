<?php

namespace Omnimap\Tests;

use Omnimap\Signer;
use PHPUnit\Framework\TestCase;

final class SignerTest extends TestCase
{
    /** HMAC-SHA1 with the secret decoded from URL-safe base64, the result in URL-safe base64. */
    public function testItSignsAsGoogleSignsAUrl(): void
    {
        $secret = 'vNIXE0xscrmjlyV-12Nj_BvUPaw=';
        $payload = '/maps/api/staticmap?center=35.0116,135.7681&zoom=12&size=640x400&key=KEY';
        $expected = strtr(base64_encode(hash_hmac('sha1', $payload, (string) base64_decode(strtr($secret, '-_', '+/')), true)), '+/', '-_');

        self::assertSame($expected, (new Signer($secret))->signPadded($payload));
        self::assertSame(rtrim($expected, '='), (new Signer($secret))->sign($payload));
        self::assertSame(28, \strlen($expected));
        self::assertTrue((new Signer($secret))->verify($payload, $expected));
        self::assertFalse((new Signer($secret))->verify($payload.'&x', $expected));
    }

    /** An empty secret signs with an empty key, as glitchr/ux-google did. */
    public function testAnEmptySecret(): void
    {
        self::assertSame(rtrim(strtr(base64_encode(hash_hmac('sha1', 'x', '', true)), '+/', '-_'), '='), (new Signer(null))->sign('x'));
        self::assertSame((new Signer(''))->sign('x'), (new Signer(null))->sign('x'));
    }
}
