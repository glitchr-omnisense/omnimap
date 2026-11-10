<?php

namespace Omnimap\Exception;

use Omnisense\Contracts\Exception\ExceptionInterface;

/**
 * Every exception omnimap throws itself (and omnisense/contracts' interface,
 * so one catch holds every family's). A storage's own failures (Flysystem's,
 * omnidrive's) and an HTTP client's pass through as they are.
 */
interface OmnimapException extends ExceptionInterface
{
}
