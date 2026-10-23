<?php

namespace Omnimap;

use Omnimap\Model\LatLng;
use Omnimap\Model\Place;

/**
 * Places from an address, addresses from a point. omnimap does not
 * geocode itself: Geocoding\GeocoderPhp asks a geocoder-php provider
 * (willdurand/geocoder), with a cache and the pace the provider's terms
 * set.
 */
interface GeocoderInterface extends ProviderInterface
{
    /** @return list<Place> the best first; none found, an empty list */
    public function geocode(string $address, ?string $locale = null, int $limit = 5): array;

    /** @return list<Place> */
    public function reverse(LatLng $position, ?string $locale = null, int $limit = 5): array;
}
