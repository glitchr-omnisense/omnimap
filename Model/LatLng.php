<?php

namespace Omnimap\Model;

use Omnimap\Exception\InvalidArgumentException;

/**
 * A point on Earth in degrees (WGS 84): a latitude from -90 to 90, a
 * longitude of any value (180.5 and -179.5 are the same meridian;
 * wrapped() brings it back between -180 and 180).
 */
final readonly class LatLng implements \JsonSerializable, \Stringable
{
    public function __construct(public float $lat, public float $lng)
    {
        if (!is_finite($lat) || !is_finite($lng)) {
            throw new InvalidArgumentException('A latitude and a longitude are finite numbers.');
        }
        if ($lat < -90.0 || $lat > 90.0) {
            throw new InvalidArgumentException(\sprintf('A latitude lies between -90 and 90, not %s.', $lat));
        }
    }

    /** @param array{lat: float|int|string, lng: float|int|string} $array */
    public static function fromArray(array $array): self
    {
        return new self((float) $array['lat'], (float) $array['lng']);
    }

    /** The same point, its longitude between -180 (included) and 180 (excluded). */
    public function wrapped(): self
    {
        $lng = fmod($this->lng + 180.0, 360.0);

        return new self($this->lat, ($lng < 0 ? $lng + 360.0 : $lng) - 180.0);
    }

    public function equals(self $other, float $tolerance = 1e-9): bool
    {
        return abs($this->lat - $other->lat) <= $tolerance && abs($this->lng - $other->lng) <= $tolerance;
    }

    /** @return array{lat: float, lng: float} */
    public function toArray(): array
    {
        return ['lat' => $this->lat, 'lng' => $this->lng];
    }

    /** @return array{lat: float, lng: float} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** "lat,lng", as URLs and geocoders write a point. */
    public function __toString(): string
    {
        return self::number($this->lat).','.self::number($this->lng);
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(\sprintf('%.7F', $value), '0'), '.') ?: '0';
    }
}
