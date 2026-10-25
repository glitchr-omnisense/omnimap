---
title: Errors
order: 50
---

# Errors

Every exception of the family implements `Omnimap\Exception\OmnimapException` (an interface
extending `\Throwable`), so one `catch (OmnimapException $e)` takes them all, and each also
extends the SPL exception that fits, for code that catches those:

| Exception | Extends | Thrown when |
|---|---|---|
| `InvalidConfigException` | `\InvalidArgumentException` | a provider is not configured or its factory not installed (`Registry`); a required option is missing (`ProviderFactory::create()`); a call needs an option that was not given: Google's Static Maps without a key, an OpenStreetMap tile fetched without a `user_agent` |
| `NotSupportedException` | `\LogicException` | a provider asked for a contract it does not implement (`Registry::renderer()`, `staticMap()`, `geocoder()`, `tiles()`); a capability declared off, called anyway (no HTTP client for an image, no PSR-18 client or key for geocoding, no client for MapLibre's `inspect()`); `cache.make: true` in Symfony on a provider that makes no image |
| `ProviderException` | `\RuntimeException` | the provider answered, and not with what was asked: a status other than 200 (401, 403, 404, 429, 5xx), a body that is not an image where an image was asked (a proxy's HTML page in a 200), geocoder-php's own errors (an error status, a quota, an answer it could not read), a MapLibre style that is not JSON or not a style |
| `UnreachableException` | `\RuntimeException` | the provider could not be reached: DNS, connection, timeout (Symfony's HTTP client's transport exceptions, PSR-18's `NetworkExceptionInterface`) |
| `InvalidArgumentException` | `\InvalidArgumentException` | a value no map can hold: a latitude past a pole or not finite, a zoom outside 0..24, a size below one pixel or not `WIDTHxHEIGHT`; a signature that is not `[A-Za-z0-9_-]{1,128}`; a picture GD cannot read; a cache format other than png, jpeg, jpg, webp |

The messages name the provider (its title) and what was asked:

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnimap\Exception\OmnimapException;
use Omnimap\MapLibre\MapLibreFactory;
use Omnimap\Model\LatLng;
use Omnimap\Model\MapView;
use Omnimap\Model\Size;
use Omnimap\OpenStreetMap\OpenStreetMapFactory;
use Omnimap\Registry;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

$down = new MockHttpClient(static fn () => new MockResponse('Unavailable', ['http_code' => 503]));
$cut = new MockHttpClient(static fn () => new MockResponse('', ['error' => 'Could not resolve host']));
$registry = new Registry([new OpenStreetMapFactory($down), new MapLibreFactory()], [
    'osm' => ['factory' => 'openstreetmap', 'options' => ['user_agent' => 'MySite/1.0']],
    'vector' => ['factory' => 'maplibre'],
    'other' => ['factory' => 'google'],
]);
$view = new MapView(new LatLng(35.0116, 135.7681), 12);

$attempts = [
    'unknown name' => fn () => $registry->get('nope'),
    'factory not installed' => fn () => $registry->get('other'),
    'capability missing' => fn () => $registry->staticMap('vector'),
    'provider answered 503' => fn () => $registry->staticMap('osm')->image($view, new Size(256, 256)),
    'network error' => fn () => (new OpenStreetMapFactory($cut))->create(['user_agent' => 'MySite/1.0'])->image($view, new Size(256, 256)),
    'geocoder switched off' => fn () => $registry->get('osm')->geocode('Kyoto'),
    'latitude past a pole' => fn () => new LatLng(91, 0),
    'zoom too deep' => fn () => new MapView(new LatLng(0, 0), 25),
];
foreach ($attempts as $label => $attempt) {
    try {
        $attempt();
    } catch (OmnimapException $e) {
        printf("%-22s %s: %s\n", $label, (new ReflectionClass($e))->getShortName(), $e->getMessage());
    }
}
```

```
unknown name           InvalidConfigException: No "nope" map provider; configured: osm, vector, other.
factory not installed  InvalidConfigException: No "google" factory for the "other" map provider; installed: openstreetmap, maplibre.
capability missing     NotSupportedException: The "vector" provider (Omnimap\MapLibre\MapLibreProvider) does not make a map image.
provider answered 503  ProviderException: OpenStreetMap answered 503 for https://tile.openstreetmap.org/12/3592/1621.png.
network error          UnreachableException: OpenStreetMap could not be reached for https://tile.openstreetmap.org/12/3592/1621.png: Could not resolve host
geocoder switched off  NotSupportedException: No PSR-18 client, no user_agent or no nominatim URL was given to the OpenStreetMap provider: no geocoding.
latitude past a pole   InvalidArgumentException: A latitude lies between -90 and 90, not 91.
zoom too deep          InvalidArgumentException: A zoom lies between 0 and 24, not 25.
```

(run on 2026-10-25 in the [harness](harness.md))

## What to catch

- Around a page's map: nothing. `MapPrinter::map()` catches whatever making a picture throws,
  hands it to its `$onError` and shows no picture (the bridges log it as a warning).
- Around a server-made image or a geocoding call: `ProviderException` and `UnreachableException`
  (the service failed or refused: retry later, show the address without a map) apart from
  `InvalidConfigException` and `NotSupportedException` (the application's configuration: fix it).
- The conformance suite holds every provider to this rule: for each operation that calls the
  service, statuses 401, 403, 404, 429, 500, 503 and an HTML page in a 200 are
  `ProviderException`s, a transport failure an `UnreachableException` ([Testing](testing.md)).
- A 429's `Retry-After` is not read: `ProviderException` carries no retry delay.

## Outside the family

Three throws are not `OmnimapException`s:

| Where | Exception | When |
|---|---|---|
| `Snapshots::make()` | `\LogicException` | no static map was given (check `canMake()` first, or call `findOrMake()`) |
| `DirectoryStorage` | `\InvalidArgumentException` | a path holding `..` (`SnapshotStore` never builds one: signatures are checked first) |
| `FlysystemStorage`, `DriveStorage` | Flysystem's `FilesystemException`, omnidrive's exceptions | the storage failed to write or delete (a missing file on read is `null`, not an exception) |

> [!WARNING]
> Known defect: `OmnimapException`'s docblock says "Every exception omnimap throws", where
> `Snapshots::make()` throws a plain `\LogicException` and `DirectoryStorage` a plain
> `\InvalidArgumentException`: a `catch (OmnimapException)` misses them.
