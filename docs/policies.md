---
title: Usage policies
order: 75
---

# Usage policies

Each provider declares the terms of the service it calls as data - omnisense/contracts'
`UsagePolicy`, read from the provider's `policy.json` (named in its `composer.json`,
`extra.omnisense.policy`): its rate limits, what its terms allow (commercial use, caching,
storage, redistribution), what they ask (attribution, a key, a User-Agent), and where and when
this was read (`provenance.verifiedAt`). What the terms do not publish is `unknown` or `null`,
never a guessed figure.

| Provider | Limits | Rights | Asks | Read at |
|---|---|---|---|---|
| openstreetmap (the OSMF servers) | Nominatim: 1 request a second for the whole application (`geocode`, `reverse`); tiles: no rate published | commercial allowed (no separate terms, access may be withdrawn); caching allowed and expected (Cache-Control, 7 days at least); storage, redistribution unknown (no offline use, no bulk download; ODbL share-alike) | "© OpenStreetMap contributors" on the map, ODbL; a User-Agent naming the application | [tile policy](https://operations.osmfoundation.org/policies/tiles/), [Nominatim policy](https://operations.osmfoundation.org/policies/nominatim/) |
| google | unknown: quotas are the Cloud project's, no default published | caching, storage and redistribution forbidden (but a geocode's coordinates for 30 days, place IDs); not with a non-Google map | the Google Maps logo, or "Google Maps"; a key | [Maps Platform Terms](https://cloud.google.com/maps-platform/terms), [Service Specific Terms](https://cloud.google.com/maps-platform/terms/maps-service-terms) |
| maplibre (OpenFreeMap's styles) | none ("no limits on the number of map views or requests") | commercial allowed; caching, storage, redistribution unknown | "OpenFreeMap © OpenMapTiles Data from OpenStreetMap" | [terms](https://openfreemap.org/tos/), [FAQ](https://openfreemap.org/) |

A provider configured with servers of the site's choice declares what it can still say:
OpenStreetMap with another tile server and another Nominatim, the data's licence and credit;
MapLibre with another style (MapTiler, Mapbox, one's own), nothing. The site reads that source's
terms and declares them.

## The registry

```php
$registry = new Registry($factories, [
    'places' => [
        'factory' => 'openstreetmap',
        'options' => ['user_agent' => 'MySite/1.0 (+https://example.org; maps@example.org)'],
        'policy' => ['rateLimits' => [['max' => 4, 'window' => 'minute', 'operations' => ['geocode']]]],
        'usage' => ['commercial' => true],
    ],
], $limiter);

$registry->policy('places');                                        // the declared policy, tightened
$registry->guarded('places', GeocoderInterface::class)->geocode('Kyoto');
```

- `policy` tightens the provider's policy, never loosens it: a limit counting the same calls with a
  higher maximum, a permission made less strict, a requirement lifted are an
  `InvalidPolicyException`. `usage` says what the site is: a use the terms forbid fails when the
  policy is first read.
- `renderer()`, `staticMap()`, `geocoder()` and `tiles()` give the provider itself, as before.
  `guarded($name, $contract)` gives it behind its guard (`Policy\GuardedRenderer`,
  `GuardedStaticMap`, `GuardedGeocoder`, `GuardedTiles`): each call that reaches a server is an
  operation named after its method - `describe` (a map the browser draws), `image`, `geocode`,
  `reverse`, `url` (a tile about to be fetched) - checked before against every limit that counts
  it and refused with `Exception\UsageLimitReachedException` (an `OmnimapException`, with
  `retryAfter`) once one is reached, the call never made; counted after, failed or not.
  `fingerprint()`, `attribution()`, `template()`, `tileSize()`, `maxZoom()` and `headers()`
  reach nothing and are not counted.
- The limiter (the registry's third argument) is in memory by default: an application served by
  many processes gives a shared one (omnibridge/symfony's, over the rate limiter's storage).
- `guard($name)->cacheTtl($ttl)` gives the time to live the terms allow (0 for Google).

The providers' own HTTP calls (Static Maps, tiles, geocoder-php's PSR-18 calls) are not read
by the kernel's Backoff: their errors stay omnimap's (a 429 a `ProviderException`, as the
conformance suite asks), and `GeocoderPhp` keeps its own pace (one Nominatim call a second per
process).
