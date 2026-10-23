<?php

namespace Omnimap\Exception;

/** The provider answered, and not with what was asked: an error status, an image that is not one. */
final class ProviderException extends \RuntimeException implements OmnimapException
{
    public static function answered(string $provider, string $what, int|string $status): self
    {
        return new self(\sprintf('%s answered %s for %s.', $provider, (string) $status, $what));
    }
}
