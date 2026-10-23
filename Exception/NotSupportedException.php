<?php

namespace Omnimap\Exception;

/** A provider asked for a capability it does not have: a static image of a provider that only draws in the browser. */
final class NotSupportedException extends \LogicException implements OmnimapException
{
    public static function capability(object $provider, string $name, string $capability): self
    {
        return new self(\sprintf('The "%s" provider (%s) does not %s.', $name, $provider::class, $capability));
    }
}
