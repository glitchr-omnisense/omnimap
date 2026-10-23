<?php

namespace Omnimap\Model;

/** What a geocoder says of a place's address; any part may be unknown. */
final readonly class Address implements \JsonSerializable
{
    public function __construct(
        public ?string $streetNumber = null,
        public ?string $streetName = null,
        public ?string $postalCode = null,
        public ?string $locality = null,
        public ?string $subLocality = null,
        public ?string $region = null,
        public ?string $country = null,
        public ?string $countryCode = null,
    ) {
    }

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return ['streetNumber' => $this->streetNumber, 'streetName' => $this->streetName, 'postalCode' => $this->postalCode, 'locality' => $this->locality, 'subLocality' => $this->subLocality, 'region' => $this->region, 'country' => $this->country, 'countryCode' => $this->countryCode];
    }

    /** @return array<string, string|null> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
