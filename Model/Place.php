<?php

namespace Omnimap\Model;

/**
 * A place a geocoder found: where it is, what it is called, its address
 * when known, the box it covers, and who said so (the provider's name) - a
 * provider's terms often ask that its answers be shown with its name, and
 * some forbid showing them on another provider's map.
 */
final readonly class Place implements \JsonSerializable
{
    public function __construct(
        public LatLng $position,
        public string $label,
        public string $providedBy,
        public ?Address $address = null,
        public ?Bounds $bounds = null,
        public ?string $id = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'position' => $this->position->toArray(),
            'label' => $this->label,
            'providedBy' => $this->providedBy,
            'address' => $this->address?->toArray(),
            'bounds' => $this->bounds?->toArray(),
            'id' => $this->id,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
