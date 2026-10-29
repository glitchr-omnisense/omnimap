<?php

namespace Omnimap\Exception;

/**
 * Every exception omnimap throws itself. A storage's own failures (Flysystem's,
 * omnidrive's) and an HTTP client's pass through as they are.
 */
interface OmnimapException extends \Throwable
{
}
