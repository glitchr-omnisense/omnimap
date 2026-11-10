<?php

namespace Omnimap\Policy;

use Omnimap\GeocoderInterface;
use Omnimap\Model\LatLng;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A provider's geocoder behind its guard: the operations "geocode" and
 * "reverse". An answer the geocoder keeps in its cache is counted too: the
 * guard checks before the call, and cannot know it will not reach the
 * provider.
 *
 * @extends Guarded<GeocoderInterface>
 */
final class GuardedGeocoder extends Guarded implements GeocoderInterface
{
    public function __construct(GeocoderInterface $provider, PolicyGuard $guard)
    {
        parent::__construct($provider, $guard);
    }

    public function geocode(string $address, ?string $locale = null, int $limit = 5): array
    {
        return $this->guard->call('geocode', fn () => $this->provider->geocode($address, $locale, $limit));
    }

    public function reverse(LatLng $position, ?string $locale = null, int $limit = 5): array
    {
        return $this->guard->call('reverse', fn () => $this->provider->reverse($position, $locale, $limit));
    }
}
