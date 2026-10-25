<?php

namespace Omnimap\Geocoding;

use Geocoder\Exception\Exception as GeocoderException;
use Geocoder\Location;
use Geocoder\Provider\Provider;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Omnimap\Exception\ProviderException;
use Omnimap\Exception\UnreachableException;
use Omnimap\Model\Address;
use Omnimap\Model\Bounds;
use Omnimap\Model\LatLng;
use Omnimap\Model\Place;
use Psr\SimpleCache\CacheInterface;

/**
 * omnimap's geocoding is geocoder-php's (willdurand/geocoder): a provider of
 * theirs asked, its Locations turned into Places. Around it, what the
 * providers' terms ask of whoever calls them: answers kept (a PSR-16 cache,
 * $ttl seconds; Nominatim: "results must be cached on your side") and a pace
 * (at least $interval seconds between two calls of this process; Nominatim:
 * "an absolute maximum of 1 request per second" - across processes, a
 * shared cache is what keeps the count down).
 */
final class GeocoderPhp
{
    private float $last = 0.0;

    public function __construct(
        private readonly Provider $provider,
        private readonly ?CacheInterface $cache = null,
        private readonly int $ttl = 2592000,
        private readonly float $interval = 0.0,
    ) {
    }

    /** @return list<Place> */
    public function geocode(string $address, ?string $locale = null, int $limit = 5): array
    {
        $query = GeocodeQuery::create($address)->withLimit($limit);

        return $this->ask('geocode|'.$address, $locale, $limit, fn () => $this->provider->geocodeQuery(null === $locale ? $query : $query->withLocale($locale)));
    }

    /** @return list<Place> */
    public function reverse(LatLng $position, ?string $locale = null, int $limit = 5): array
    {
        $query = ReverseQuery::fromCoordinates($position->lat, $position->lng)->withLimit($limit);

        return $this->ask('reverse|'.$position, $locale, $limit, fn () => $this->provider->reverseQuery(null === $locale ? $query : $query->withLocale($locale)));
    }

    /**
     * @param callable(): iterable<Location> $call
     *
     * @return list<Place>
     */
    private function ask(string $question, ?string $locale, int $limit, callable $call): array
    {
        $key = 'omnimap.geocoder.'.sha1($this->provider->getName().'|'.$question.'|'.$locale.'|'.$limit);
        $cached = $this->cache?->get($key);
        if (\is_array($cached)) {
            return array_values(array_filter($cached, static fn ($place) => $place instanceof Place));
        }

        $wait = $this->last + $this->interval - microtime(true);
        if ($wait > 0) {
            usleep((int) ceil($wait * 1e6));
        }
        try {
            $locations = $call();
        } catch (\Psr\Http\Client\NetworkExceptionInterface $e) {
            throw new UnreachableException(\sprintf('%s could not be reached for "%s": %s', $this->provider->getName(), $question, $e->getMessage()), 0, $e);
        } catch (GeocoderException|\Psr\Http\Client\ClientExceptionInterface $e) {
            throw new ProviderException(\sprintf('%s could not answer "%s": %s', $this->provider->getName(), $question, $e->getMessage()), 0, $e);
        } catch (\TypeError|\ValueError|\JsonException $e) {
            // geocoder-php's providers read the body as the shape they expect: anything else is an answer, not a bug of the caller's.
            throw new ProviderException(\sprintf('%s answered "%s" with something it could not read: %s', $this->provider->getName(), $question, $e->getMessage()), 0, $e);
        } finally {
            $this->last = microtime(true);
        }

        $places = [];
        foreach ($locations as $location) {
            $place = self::place($location);
            if (null !== $place) {
                $places[] = $place;
            }
        }
        $this->cache?->set($key, $places, $this->ttl);

        return $places;
    }

    public static function place(Location $location): ?Place
    {
        $coordinates = $location->getCoordinates();
        if (null === $coordinates) {
            return null;
        }
        $country = $location->getCountry();
        $region = $location->getAdminLevels()->count() > 0 ? $location->getAdminLevels()->first()->getName() : null;
        $address = new Address(
            self::text($location->getStreetNumber()),
            self::text($location->getStreetName()),
            self::text($location->getPostalCode()),
            self::text($location->getLocality()),
            self::text($location->getSubLocality()),
            self::text($region),
            self::text($country?->getName()),
            self::text($country?->getCode()),
        );
        $bounds = $location->getBounds();

        return new Place(
            new LatLng($coordinates->getLatitude(), $coordinates->getLongitude()),
            self::label($location, $address),
            $location->getProvidedBy(),
            $address,
            null === $bounds ? null : new Bounds(new LatLng($bounds->getSouth(), $bounds->getWest()), new LatLng($bounds->getNorth(), $bounds->getEast())),
            self::id($location),
        );
    }

    /** The provider's own one-line name of the place (Google's formatted address, Nominatim's display name), else the address's parts. */
    private static function label(Location $location, Address $address): string
    {
        foreach (['getFormattedAddress', 'getDisplayName'] as $method) {
            if (method_exists($location, $method)) {
                $label = $location->{$method}();
                if (\is_string($label) && '' !== $label) {
                    return $label;
                }
            }
        }
        $street = trim(($address->streetNumber ?? '').' '.($address->streetName ?? ''));

        return implode(', ', array_filter([$street, trim(($address->postalCode ?? '').' '.($address->locality ?? '')), $address->region, $address->country], static fn (?string $part) => null !== $part && '' !== $part));
    }

    /** Google's place_id; OpenStreetMap's object, "relation/357794". */
    private static function id(Location $location): ?string
    {
        if (method_exists($location, 'getId') && \is_string($id = $location->getId()) && '' !== $id) {
            return $id;
        }
        if (method_exists($location, 'getOSMType') && method_exists($location, 'getOSMId') && null !== $location->getOSMId()) {
            $type = $location->getOSMType();
            $id = $location->getOSMId();

            return (\is_scalar($type) ? (string) $type : '').'/'.(\is_scalar($id) ? (string) $id : '');
        }

        return null;
    }

    private static function text(mixed $value): ?string
    {
        return \is_scalar($value) && '' !== (string) $value ? (string) $value : null;
    }
}
