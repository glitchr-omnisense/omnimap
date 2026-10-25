---
title: Writing a provider
order: 80
---

## Writing a provider

A provider is a package `omnimap/<name>` with a factory and a provider class:

- the factory extends `Omnimap\ProviderFactory`: `populate()` names it, lists its required
  options and their defaults; `build()` returns the provider. Clients (HTTP, PSR-18) and caches
  come through the factory's constructor, never created inside.
- the provider implements the contracts it can answer - `MapRendererInterface`,
  `StaticMapInterface`, `GeocoderInterface`, `TileSourceInterface` - and says so in
  `capabilities()`, with the hosts a visitor's browser reaches and the terms that apply.
- geocoding goes through `Geocoding\GeocoderPhp` over a geocoder-php provider: omnimap does not
  parse a geocoding API itself.
- a renderer's `describe()` is plain data; its driver (`assets/<name>.js`) is an ES module
  exporting `draw(element, description, options)`.
- `fingerprint()` is everything the picture of a view depends on: two views with the same
  fingerprint must look the same.
- tests run in the core's harness (`docker/harness/plugins.php` lists the providers), with a
  `BareTest` loading every class with the package's requirements alone.
- the bridges list the factories they build by class (omnibridge/symfony's `OmnimapExtension`,
  omnibridge/laravel's `MapServiceProvider::FACTORIES`): a new provider is wired there too, or an
  application registers its factory itself (Symfony: a service tagged `omnimap.provider_factory`).

The package layout (`omnimap/openstreetmap`):

```
openstreetmap/
  OpenStreetMapFactory.php      # name, title, options and their defaults, the provider built
  OpenStreetMapProvider.php     # the contracts it answers, its capabilities, its description
  assets/leaflet.js             # the browser driver: draw(element, description, options)
  Tests/OpenStreetMapContractTest.php   # the conformance suite
  Tests/OpenStreetMapProviderTest.php   # what is particular to the provider
  Tests/BareTest.php            # every class loads with the package's requirements alone
  Tests/LiveTest.php            # #[Group('live')]: the real service, excluded by default
```

### A whole provider

A raster tile service with a key: a renderer, a static map made of its tiles, a tile source. This
code, with the contract test after it, ran green in the [harness](harness.md) on 2026-10-25 (the
suite's 8 tests).

```php
<?php

use Omnimap\Capabilities;
use Omnimap\Config;
use Omnimap\Exception\NotSupportedException;
use Omnimap\MapRendererInterface;
use Omnimap\Model\Image;
use Omnimap\Model\MapView;
use Omnimap\Model\Size;
use Omnimap\ProviderFactory;
use Omnimap\ProviderInterface;
use Omnimap\Static\TileStitcher;
use Omnimap\StaticMapInterface;
use Omnimap\TileSourceInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AcmeFactory extends ProviderFactory
{
    public function __construct(private readonly ?HttpClientInterface $http = null)
    {
    }

    protected function populate(Config $c): void
    {
        $c->defaults([
            'omnimap.factory_name' => 'acme',
            'omnimap.factory_title' => 'Acme Maps',
            'omnimap.required_options' => ['api_key'],
            'max_zoom' => 18,
        ]);
    }

    protected function build(Config $c): ProviderInterface
    {
        return new AcmeProvider((string) $c->string('api_key'), $c->int('max_zoom', 18), $this->http);
    }
}

final class AcmeProvider implements MapRendererInterface, StaticMapInterface, TileSourceInterface
{
    public function __construct(private readonly string $key, private readonly int $maxZoom, private readonly ?HttpClientInterface $http)
    {
    }

    public function getName(): string { return 'acme'; }
    public function getTitle(): string { return 'Acme Maps'; }

    public function capabilities(): Capabilities
    {
        return new Capabilities(renderer: true, staticMap: null !== $this->http, tiles: true, browserHosts: ['tiles.acme.example'], terms: ['https://acme.example/terms']);
    }

    public function describe(MapView $view): array
    {
        return ['provider' => 'acme', 'consent' => 'MAP', 'view' => $view->toArray(), 'tiles' => ['template' => $this->template()], 'attribution' => $this->attribution()];
    }

    public function fingerprint(MapView $view): string
    {
        return 'acme|'.json_encode([$view->center, $view->zoom, $view->markers, $view->controls], \JSON_THROW_ON_ERROR);
    }

    public function attribution(): string { return '&copy; Acme'; }

    public function image(MapView $view, Size $size, int $scale = 1): Image
    {
        $http = $this->http ?? throw new NotSupportedException('No HTTP client was given to Acme: no image.');

        return (new TileStitcher($this, $http))->image($view, $size, $scale);
    }

    public function template(): string { return 'https://tiles.acme.example/{z}/{x}/{y}.png?key='.rawurlencode($this->key); }
    public function url(int $zoom, int $x, int $y): string { return str_replace(['{z}', '{x}', '{y}'], [(string) $zoom, (string) $x, (string) $y], $this->template()); }
    public function tileSize(): int { return 256; }
    public function maxZoom(): int { return $this->maxZoom; }
    public function headers(): array { return ['User-Agent' => 'acme-tests/1.0']; }
}
```

The key is in the tile URL the browser reaches: a service whose keys must stay secret is drawn
from the server's images only (no renderer), or through a proxy route of the host's.

### The conformance suite

`Omnimap\Testing\ProviderContractTests` is what every provider is held to, as PHPUnit tests,
offline: the provider's test case uses the trait and gives its factory built over the HTTP
client the suite hands it (recorded answers, no network).

```php
final class AcmeContractTest extends TestCase
{
    use ProviderContractTests;

    protected function contractFactory(?HttpClientInterface $http): ProviderFactoryInterface
    {
        return new AcmeFactory($http);
    }

    protected function contractOptions(): array
    {
        return ['api_key' => 'test-key'];
    }
}
```

```
Acme Contract
 ✔ The factory names what it builds and the registry builds it once
 ✔ A missing required option is refused by name
 ✔ Capabilities are what the provider answers
 ✔ A renderer describes the view as plain data
 ✔ A static map is an image of the size asked
 ✔ Tile addresses are complete
 ✔ A geocoder answers places of its own
 ✔ Errors are the familys exceptions
```

A provider that geocodes gives its factory a PSR-18 client over the suite's HTTP client, and its
recorded geocoding answer:

```php
final class MyMapContractTest extends TestCase
{
    use ProviderContractTests;

    protected function contractFactory(?HttpClientInterface $http): ProviderFactoryInterface
    {
        return new MyMapFactory($http, null === $http ? null : new Psr18Client($http), new ArrayCache());
    }

    protected function contractOptions(): array { return ['api_key' => 'test-key']; }   // every capability on

    // Optional: the recorded answer of a request that succeeds (default: a PNG tile for any URL).
    protected function contractAnswer(string $method, string $url, array $options): array { ... }
}
```

It checks the factory and the Registry (the same name, built once, a missing required option
refused by name), that `capabilities()` is what the provider answers (a capability declared is
implemented; one implemented but off, without its client, throws `NotSupportedException`), a
renderer's description (plain data, the provider's name for its driver, the view, the consent, every
address in it on a declared browser host, a fingerprint that follows centre, zoom and markers), a
static map's image (the bytes are the image type it says, no larger than asked), a tile source's
addresses, a geocoder's places, and the family's rule for errors:

- an answer other than 200, or a body that is not what was asked (a proxy's HTML page in a 200,
  JSON of another shape), is a `ProviderException`;
- a request that never got an answer (DNS, connection, timeout) is an `UnreachableException`;
- a capability the provider does not have, or has switched off, is a `NotSupportedException`.

The provider's require-dev holds `phpunit/phpunit` and `symfony/http-client`. The core runs the suite on
its own `Testing\TestTiles` (`Tests/TestTilesContractTest.php`). Each test and each hook:
[Testing](testing.md#conformance-suite-each-test).

### Checklist

- [ ] the factory names the provider (`omnimap.factory_name`, the configuration's `factory`), its
  title, its required options and every default; building calls nothing;
- [ ] `capabilities()` is what the provider answers, built without a client too;
- [ ] `describe()` holds to `schema/map-description.schema.json` (add the factory to
  `Tests/MapDescriptionSchemaTest.php`'s list in the core) and names every host the browser
  reaches in `browserHosts`;
- [ ] the driver `assets/<name>.js` exports `{ draw }` and uses the `loadScript()`/`loadStyle()`
  it is given;
- [ ] errors are `ProviderException` / `UnreachableException`, a capability off
  `NotSupportedException`;
- [ ] the service's terms are in `terms`, its rate limits and its required User-Agent kept in code;
- [ ] `docs/index.md`: options, capabilities, behaviour, errors, verified and not
  (CONTRIBUTING.md of omnimap/maplibre is the family's guide to testing, documenting and
  releasing a provider).
