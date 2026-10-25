# Writing a provider

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

## The conformance suite

`Omnimap\Testing\ProviderContractTests` is what every provider is held to, as PHPUnit tests,
offline: the provider's test case uses the trait and gives its factory built over the HTTP
client the suite hands it (recorded answers, no network).

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
its own `Testing\TestTiles` (`Tests/TestTilesContractTest.php`).
