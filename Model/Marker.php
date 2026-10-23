<?php

namespace Omnimap\Model;

/** A pin on the map: where, its title (shown on hover by the renderers that can), its colour (a CSS hex colour). */
final readonly class Marker implements \JsonSerializable
{
    public function __construct(
        public LatLng $position,
        public ?string $title = null,
        public ?string $color = null,
    ) {
    }

    /** @return array{position: array{lat: float, lng: float}, title: ?string, color: ?string} */
    public function toArray(): array
    {
        return ['position' => $this->position->toArray(), 'title' => $this->title, 'color' => $this->color];
    }

    /** @return array{position: array{lat: float, lng: float}, title: ?string, color: ?string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
