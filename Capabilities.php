<?php

namespace Omnimap;

/**
 * What a provider is, said before it is asked anything: what it can do, and
 * who it makes the visitor's browser or the server talk to.
 */
final readonly class Capabilities
{
    /**
     * @param bool         $renderer          it draws a map in the browser (MapRendererInterface)
     * @param bool         $staticMap         it makes an image of a map on the server (StaticMapInterface)
     * @param bool         $geocoder          it finds places from an address and addresses from a point (GeocoderInterface)
     * @param bool         $tiles             it gives tiles, one URL per tile (TileSourceInterface)
     * @param list<string> $browserHosts      the hosts a visitor's browser reaches when the map is drawn live: what a consent asks about
     * @param bool         $cookies           whether its browser script sets or reads cookies
     * @param list<string> $terms             the terms whoever runs it must follow (URLs)
     */
    public function __construct(
        public bool $renderer = false,
        public bool $staticMap = false,
        public bool $geocoder = false,
        public bool $tiles = false,
        public array $browserHosts = [],
        public bool $cookies = false,
        public array $terms = [],
    ) {
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
