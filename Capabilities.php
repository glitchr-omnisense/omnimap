<?php

namespace Omnimap;

use Omnisense\Contracts\Policy\UsagePolicy;
use Omnisense\Contracts\Provider\CapabilitiesInterface;

/**
 * What a provider is, said before it is asked anything: what it can do, who
 * it makes the visitor's browser or the server talk to, and its usage
 * policy (omnisense/contracts' UsagePolicy): its rate limits, what its
 * terms allow (caching, storage, commercial use) and ask (attribution, a
 * key, a User-Agent).
 *
 * $terms, written before the policy, stays: read from the policy's
 * provenance when it is given and $terms is not; a provider that gives
 * $terms alone gets a policy made of them, its limits unknown
 * (UsagePolicy::described()).
 */
final readonly class Capabilities implements CapabilitiesInterface
{
    /**
     * @var list<string>
     *
     * @deprecated since 1.x, read policy()->provenance (terms, policy)
     */
    public array $terms;

    private UsagePolicy $policy;

    /**
     * @param bool         $renderer          it draws a map in the browser (MapRendererInterface)
     * @param bool         $staticMap         it makes an image of a map on the server (StaticMapInterface)
     * @param bool         $geocoder          it finds places from an address and addresses from a point (GeocoderInterface)
     * @param bool         $tiles             it gives tiles, one URL per tile (TileSourceInterface)
     * @param list<string> $browserHosts      the hosts a visitor's browser reaches when the map is drawn live: what a consent asks about
     * @param bool         $cookies           whether its browser script sets or reads cookies
     * @param list<string> $terms             the terms whoever runs it must follow (URLs)
     * @param ?UsagePolicy $policy            its usage policy; made of $terms when null
     */
    public function __construct(
        public bool $renderer = false,
        public bool $staticMap = false,
        public bool $geocoder = false,
        public bool $tiles = false,
        public array $browserHosts = [],
        public bool $cookies = false,
        array $terms = [],
        ?UsagePolicy $policy = null,
    ) {
        $this->policy = $policy ?? UsagePolicy::described(UsagePolicy::UNNAMED, null, null, $terms);
        $this->terms = [] !== $terms || null === $policy ? $terms : array_values(array_unique(array_filter([$policy->provenance->terms, $policy->provenance->policy])));
    }

    public function policy(): UsagePolicy
    {
        return $this->policy;
    }

    /** @return list<string> what it can be asked, by contract: renderer, staticMap, geocoder, tiles */
    public function contracts(): array
    {
        return array_keys(array_filter(['renderer' => $this->renderer, 'staticMap' => $this->staticMap, 'geocoder' => $this->geocoder, 'tiles' => $this->tiles]));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['contracts' => $this->contracts(), 'renderer' => $this->renderer, 'staticMap' => $this->staticMap, 'geocoder' => $this->geocoder, 'tiles' => $this->tiles, 'browserHosts' => $this->browserHosts, 'cookies' => $this->cookies, 'terms' => $this->terms];
    }
}
