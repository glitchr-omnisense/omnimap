<?php

namespace Omnimap\Exception;

/** A value no map can hold: a latitude past a pole, a size of zero, a zoom below 0. */
final class InvalidArgumentException extends \InvalidArgumentException implements OmnimapException
{
}
