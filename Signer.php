<?php

namespace Omnimap;

/**
 * HMAC-SHA1 with a secret given in URL-safe base64, the signature in
 * URL-safe base64: Google's rule for signing a Maps URL, and the rule
 * glitchr/ux-google named its captured maps by - kept, so the pictures
 * captured before omnimap keep their names. An empty secret signs with an
 * empty key, as ux-google did.
 */
final class Signer
{
    public function __construct(private readonly ?string $secret)
    {
    }

    /** The signature, without base64's "=" padding (ux-google's names). */
    public function sign(string $payload): string
    {
        return rtrim($this->signPadded($payload), '=');
    }

    /** The signature with its padding: what Google expects in a URL's `signature=`. */
    public function signPadded(string $payload): string
    {
        return self::encode(hash_hmac('sha1', $payload, self::decode((string) $this->secret), true));
    }

    public function verify(string $payload, string $signature): bool
    {
        return hash_equals($this->sign($payload), rtrim($signature, '='));
    }

    public static function encode(string $bytes): string
    {
        return strtr(base64_encode($bytes), '+/', '-_');
    }

    public static function decode(string $text): string
    {
        return (string) base64_decode(strtr($text, '-_', '+/'));
    }
}
