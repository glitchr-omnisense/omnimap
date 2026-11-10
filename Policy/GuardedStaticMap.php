<?php

namespace Omnimap\Policy;

use Omnimap\Model\Image;
use Omnimap\Model\MapView;
use Omnimap\Model\Size;
use Omnimap\StaticMapInterface;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A provider's map image behind its guard: each image() is the operation
 * "image" (one Static Maps request, or the tiles of the view fetched by the
 * server).
 *
 * @extends Guarded<StaticMapInterface>
 */
final class GuardedStaticMap extends Guarded implements StaticMapInterface
{
    public function __construct(StaticMapInterface $provider, PolicyGuard $guard)
    {
        parent::__construct($provider, $guard);
    }

    public function image(MapView $view, Size $size, int $scale = 1): Image
    {
        return $this->guard->call('image', fn () => $this->provider->image($view, $size, $scale));
    }
}
