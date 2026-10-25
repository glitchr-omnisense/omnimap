---
title: Geocoding
order: 33
---

## Geocoding

A geocoder (`GeocoderInterface`) finds places from an address (`geocode()`) and addresses from a
point (`reverse()`). omnimap does not read a geocoding API itself: each provider puts one of
[geocoder-php](https://geocoder-php.org)'s providers (`willdurand/geocoder`) behind
`Omnimap\Geocoding\GeocoderPhp`, which turns its answers into omnimap's `Place`s, keeps them in a
PSR-16 cache and paces the calls. The providers that geocode take a PSR-18 client for
geocoder-php, beside their Symfony HTTP client.

### Places from an address, an address from a point

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnimap\Google\GoogleFactory;
use Omnimap\Model\LatLng;
use Omnimap\Testing\ArrayCache;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpClient\Response\MockResponse;

// Google's answer, written here; HttpClient::create() in production.
$answer = json_encode(['status' => 'OK', 'results' => [[
    'formatted_address' => 'Osaka, Japan',
    'place_id' => 'ChIJ4eIGNFXmAGAR5y9q5G7BW8U',
    'address_components' => [
        ['long_name' => 'Osaka', 'short_name' => 'Osaka', 'types' => ['locality', 'political']],
        ['long_name' => 'Japan', 'short_name' => 'JP', 'types' => ['country', 'political']],
    ],
    'geometry' => ['location' => ['lat' => 34.6937378, 'lng' => 135.5021651], 'location_type' => 'APPROXIMATE'],
    'types' => ['locality', 'political'],
]]]);
$requests = 0;
$http = new MockHttpClient(function (string $method, string $url) use ($answer, &$requests) {
    ++$requests;
    echo "GET ", preg_replace('~key=[^&]+~', 'key=…', $url), "\n";

    return new MockResponse($answer, ['response_headers' => ['content-type' => 'application/json']]);
});

$google = (new GoogleFactory($http, new Psr18Client($http), new ArrayCache()))->create(['server_key' => 'server-key', 'language' => 'en']);

foreach ($google->geocode('Osaka', limit: 1) as $place) {
    echo $place->label, ' ', $place->position, ' ', $place->address->countryCode, ' ', $place->id, ' (', $place->providedBy, ")\n";
}
$google->geocode('Osaka', limit: 1);
echo $requests, " request\n";
echo count($google->reverse(new LatLng(34.6937, 135.5022), 'ja', 1)), " place\n";
```

```
GET https://maps.googleapis.com/maps/api/geocode/json?address=Osaka&language=en&key=…
Osaka, Japan 34.6937378,135.5021651 JP ChIJ4eIGNFXmAGAR5y9q5G7BW8U (google_maps)
1 request
GET https://maps.googleapis.com/maps/api/geocode/json?latlng=34.693700,135.502200&language=ja&key=…
1 place
```

(run on 2026-10-25 in the [harness](harness.md), with `nyholm/psr7` installed for
`Psr18Client`; the answer is written here, Google was not called)

The second `geocode('Osaka')` was answered by the cache. The locale given (`ja`) went to Google
instead of the provider's `language`. In production the client is `HttpClient::create()` (or the
application's) and the cache the application's PSR-16 pool.

### What GeocoderPhp does

`new GeocoderPhp(Geocoder\Provider\Provider $provider, ?CacheInterface $cache = null, int $ttl = 2592000, float $interval = 0.0)`:

- `geocode($address, $locale, $limit)` asks `GeocodeQuery::create($address)->withLimit($limit)`
  (with the locale when not `null`); `reverse($position, $locale, $limit)` asks
  `ReverseQuery::fromCoordinates()`;
- **the cache**: the answer is kept under `omnimap.geocoder.` + SHA-1 of the provider's name, the
  question (`geocode|<address>` or `reverse|<lat,lng>`), the locale and the limit, for `$ttl`
  seconds (30 days by default: Nominatim's policy asks for results cached on the caller's side).
  A cached answer is returned without waiting and without a call; an empty answer is kept too;
- **the pace**: before a call, it waits until `$interval` seconds have passed since the end of
  the previous call of this object (`usleep`). It paces one PHP process; across processes, a
  shared cache is what keeps the count down;
- **the answer**: each geocoder-php `Location` with coordinates becomes a `Place` (see
  [Concepts](concepts.md#place-and-address)); one without coordinates is dropped;
- **the errors**: a PSR-18 `NetworkExceptionInterface` is an `UnreachableException`; geocoder-php's
  own exceptions (an error status, an invalid answer, a quota) and other PSR-18 client exceptions
  are a `ProviderException`; a `TypeError`, `ValueError` or `JsonException` raised while
  geocoder-php reads a body it did not expect is a `ProviderException` too. Each message names the
  provider and the question.

`GeocoderPhp::place(Location $location): ?Place` is public: it converts one geocoder-php answer.

### The providers that geocode

| Provider | geocoder-php provider | Needs | Locale | Pace |
|---|---|---|---|---|
| `google` | `Geocoder\Provider\GoogleMaps\GoogleMaps` (`geocoder-php/google-maps-provider`), with `region` and the server key | a PSR-18 client, and `server_key` or `api_key` | the call's, else the `language` option | none |
| `openstreetmap` | `Geocoder\Provider\Nominatim\Nominatim` (`geocoder-php/nominatim-provider`), at `nominatim` with `user_agent` and `referer` | a PSR-18 client, a `user_agent`, a non-empty `nominatim` URL | the call's | `nominatim_interval` (1 second) |
| `maplibre` | - | - | - | - |

Both keep answers for `geocoder_ttl` seconds (30 days) in the cache given to their factory's
constructor (none: no cache). Without what they need, `capabilities()->geocoder` is `false` and
`geocode()` / `reverse()` throw `NotSupportedException` before any request.

### A geocoder of one's own choice

Any geocoder-php provider can sit behind `GeocoderPhp` (Here, Mapbox, Photon, a chain of several);
`GeocoderPhp` is not a `GeocoderInterface` itself (it is not a provider: it has no name, no
capabilities). A provider package exposes it: `omnimap/openstreetmap`'s provider takes any
`GeocoderPhp` in its constructor, which is also how an application's test gives it a fixed
answer ([Testing](testing.md#a-geocoder-that-knows-its-answers)).

```php
use Geocoder\Provider\Photon\Photon;                      // geocoder-php/photon-provider
use Omnimap\Geocoding\GeocoderPhp;
use Omnimap\OpenStreetMap\OpenStreetMapProvider;

$geocoder = new OpenStreetMapProvider(geocoder: new GeocoderPhp(Photon::withKomootServer($psr18), $cache, 86400, 1.0));
$geocoder->geocode('Strasbourg');
```

(not run: `geocoder-php/photon-provider` is not installed in the harness)

### Terms

Nominatim's policy (https://operations.osmfoundation.org/policies/nominatim/): at most one request
a second, an identifying User-Agent or Referer, results cached, no autocomplete, no systematic
queries. Google's terms (https://cloud.google.com/maps-platform/terms) restrict caching
Geocoding results and showing them on a map other than Google's: `Place::$providedBy` says whose
answer a place is.
