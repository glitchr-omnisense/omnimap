<?php

namespace Omnimap\Model;

/**
 * A box on the map: its south-west and north-east corners. A box across the
 * antimeridian has a western longitude greater than its eastern one.
 */
final readonly class Bounds implements \JsonSerializable
{
    public function __construct(public LatLng $southWest, public LatLng $northEast)
    {
    }

    public function contains(LatLng $point): bool
    {
        if ($point->lat < $this->southWest->lat || $point->lat > $this->northEast->lat) {
            return false;
        }
        $lng = $point->wrapped()->lng;
        $west = $this->southWest->wrapped()->lng;
        $east = $this->northEast->wrapped()->lng;

        return $west <= $east ? $lng >= $west && $lng <= $east : $lng >= $west || $lng <= $east;
    }

    /** @return array{south: float, west: float, north: float, east: float} */
    public function toArray(): array
    {
        return ['south' => $this->southWest->lat, 'west' => $this->southWest->lng, 'north' => $this->northEast->lat, 'east' => $this->northEast->lng];
    }

    /** @return array{south: float, west: float, north: float, east: float} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
