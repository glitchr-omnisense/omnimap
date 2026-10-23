<?php

namespace Omnimap\Exception;

/** A provider asked for with options it cannot work with, or not configured at all. */
final class InvalidConfigException extends \InvalidArgumentException implements OmnimapException
{
}
