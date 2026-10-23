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
