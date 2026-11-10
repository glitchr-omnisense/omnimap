<?php

namespace Omnimap\Policy;

use Omnimap\MapRendererInterface;
use Omnimap\Model\MapView;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A provider's map in the browser behind its guard: describe() - one map
 * the visitor's browser draws, its tiles and library fetched there - is the
 * operation "describe"; fingerprint() and attribution() reach nothing and
 * are not counted.
 *
 * @extends Guarded<MapRendererInterface>
 */
final class GuardedRenderer extends Guarded implements MapRendererInterface
{
    public function __construct(MapRendererInterface $provider, PolicyGuard $guard)
    {
        parent::__construct($provider, $guard);
    }

    public function describe(MapView $view): array
    {
        return $this->guard->call('describe', fn () => $this->provider->describe($view));
    }

    public function fingerprint(MapView $view): string
    {
        return $this->provider->fingerprint($view);
    }

    public function attribution(): string
    {
        return $this->provider->attribution();
    }
}
