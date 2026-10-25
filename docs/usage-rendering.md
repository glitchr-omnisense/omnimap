---
title: A live map in the browser
order: 31
---

# A live map in the browser

A renderer (`MapRendererInterface`) turns a `MapView` into a description; the page carries it in a
`data-` attribute; `assets/omnimap.js` reads it and hands it to the provider's driver, which loads
the provider's library and draws the map once the visitor's consent allows it. PHP sends no code
to the browser and calls nothing: describing a view is plain data.

## Describing a view

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnimap\MapHtml;
use Omnimap\MapLibre\MapLibreFactory;
use Omnimap\Model\LatLng;
use Omnimap\Model\MapView;
use Omnimap\Model\Marker;
use Omnimap\OpenStreetMap\OpenStreetMapFactory;
use Omnimap\Registry;

$registry = new Registry([new OpenStreetMapFactory(), new MapLibreFactory()], [
    'maps' => ['factory' => 'openstreetmap', 'options' => ['user_agent' => 'MySite/1.0 (+https://example.org)']],
    'vector' => ['factory' => 'maplibre'],
]);

$view = (new MapView(new LatLng(48.5818, 7.7509), 14, [new Marker(new LatLng(48.5818, 7.7509), 'Cathédrale', '#1a73e8')], id: 'map'))
    ->withOptions('openstreetmap', ['map' => ['scrollWheelZoom' => false]])
    ->withOptions('maplibre', ['map' => ['pitch' => 45]]);

$description = $registry->renderer('maps')->describe($view);
echo json_encode($description, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n\n";
echo (new MapHtml())->live('map', $description), "\n";
echo json_encode($registry->renderer('vector')->describe($view)['options'], JSON_UNESCAPED_SLASHES), "\n";
echo $registry->renderer('maps')->fingerprint($view), "\n";
```

```
{
    "provider": "openstreetmap",
    "consent": "MAP",
    "view": {
        "id": "map",
        "center": {
            "lat": 48.5818,
            "lng": 7.7509
        },
        "zoom": 14,
        "markers": [
            {
                "position": {
                    "lat": 48.5818,
                    "lng": 7.7509
                },
                "title": "Cathédrale",
                "color": "#1a73e8"
            }
        ],
        "controls": true
    },
    "library": {
        "script": "https://unpkg.com/leaflet@1.9.4/dist/leaflet.js",
        "scriptIntegrity": "sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=",
        "style": "https://unpkg.com/leaflet@1.9.4/dist/leaflet.css",
        "styleIntegrity": "sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    },
    "tiles": {
        "template": "https://tile.openstreetmap.org/{z}/{x}/{y}.png",
        "maxZoom": 19,
        "tileSize": 256,
        "attribution": "&copy; <a href=\"https://www.openstreetmap.org/copyright\">OpenStreetMap</a> contributors"
    },
    "options": {
        "map": {
            "scrollWheelZoom": false
        }
    },
    "attribution": "&copy; <a href=\"https://www.openstreetmap.org/copyright\">OpenStreetMap</a> contributors"
}

<div id="map" data-omnimap="map" data-omnimap-description="{&quot;provider&quot;:&quot;openstreetmap&quot;,&quot;consent&quot;:&quot;MAP&quot;,&quot;view&quot;:{&quot;id&quot;:&quot;map&quot;,&quot;center&quot;:{&quot;lat&quot;:48.5818,&quot;lng&quot;:7.7509},&quot;zoom&quot;:14,&quot;markers&quot;:[{&quot;position&quot;:{&quot;lat&quot;:48.5818,&quot;lng&quot;:7.7509},&quot;title&quot;:&quot;Cathédrale&quot;,&quot;color&quot;:&quot;#1a73e8&quot;}],&quot;controls&quot;:true},&quot;library&quot;:{&quot;script&quot;:&quot;https://unpkg.com/leaflet@1.9.4/dist/leaflet.js&quot;,&quot;scriptIntegrity&quot;:&quot;sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=&quot;,&quot;style&quot;:&quot;https://unpkg.com/leaflet@1.9.4/dist/leaflet.css&quot;,&quot;styleIntegrity&quot;:&quot;sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=&quot;},&quot;tiles&quot;:{&quot;template&quot;:&quot;https://tile.openstreetmap.org/{z}/{x}/{y}.png&quot;,&quot;maxZoom&quot;:19,&quot;tileSize&quot;:256,&quot;attribution&quot;:&quot;&amp;copy; &lt;a href=\&quot;https://www.openstreetmap.org/copyright\&quot;&gt;OpenStreetMap&lt;/a&gt; contributors&quot;},&quot;options&quot;:{&quot;map&quot;:{&quot;scrollWheelZoom&quot;:false}},&quot;attribution&quot;:&quot;&amp;copy; &lt;a href=\&quot;https://www.openstreetmap.org/copyright\&quot;&gt;OpenStreetMap&lt;/a&gt; contributors&quot;}"></div>

{"map":{"pitch":45},"preserveDrawingBuffer":true}
openstreetmap|["https:\/\/tile.openstreetmap.org\/{z}\/{x}\/{y}.png",{"lat":48.5818,"lng":7.7509},14,[{"position":{"lat":48.5818,"lng":7.7509},"title":"Cathédrale","color":"#1a73e8"}],true]
```

(run on 2026-10-25 in the [harness](harness.md))

The same view went to two providers: each read its own options (`openstreetmap`'s `map`,
`maplibre`'s `map`) and ignored the other's. No factory was given an HTTP client: describing
needs none.

## The description

What every renderer returns, as `schema/map-description.schema.json` (JSON Schema 2020-12) defines
it; `Tests/MapDescriptionSchemaTest.php` checks every installed renderer's description against it.

| Key | Type | Required | |
|---|---|---|---|
| `provider` | string, `^[a-z0-9][a-z0-9_-]*$` | yes | the provider's name: the driver `omnimap.js` picks (`register(name, driver)`) |
| `consent` | string, not empty | yes | the consent feature the map waits for before reaching a third party: `MAP` for every provider today |
| `view` | object | yes | `MapView::toArray()`: `id`, `center` (`lat` -90..90, `lng` -180..180), `zoom` (0..24), `markers` (each `position`, `title`, `color`, the last two nullable), `controls`; no other key |
| `attribution` | string | yes | HTML shown beside a kept picture of the map; may be empty when the live map shows its own |
| `library` | object of strings | no | where the browser loads the provider's library: script, style, module, their integrity, a key |
| `options` | object | no | the provider's options for its library, from `MapView::options(<provider>)` |

Any other key is the provider's own (`tiles` for OpenStreetMap, `style` for MapLibre). The schema's
`$id` is `https://raw.githubusercontent.com/glitchr-studio/omnimap/1.x/schema/map-description.schema.json`.

Each provider's keys:

| Provider | `library` | Own keys | `options` | `attribution` |
|---|---|---|---|---|
| `google` | `key` (`api_key`), `v` (`version`), `language`, `region` - the empty ones left out | - | `styles` (list, `[]`), `zoomStyles` (object or `null`), `map` (object, `{}`) | `''`: the live map draws Google's own |
| `openstreetmap` | `script`, `scriptIntegrity`, `style`, `styleIntegrity` (Leaflet; the empty ones left out) | `tiles`: `template`, `maxZoom`, `tileSize`, `attribution` | `map` (object, `{}`) | the `attribution` option |
| `maplibre` | `script`, `scriptIntegrity`, `style`, `styleIntegrity`; or `module`, `style`, `styleIntegrity` when `library_module` is set | `style`: the style's URL | `map` (object, `{}`), `preserveDrawingBuffer` (bool) | the `attribution` option |

## A view's options of one provider

`MapView::$options` holds, under a provider's name, what only that provider understands; each
renderer reads its own and ignores the others. What each reads:

| Provider | Key | |
|---|---|---|
| `google` | `styles` | a list of `{name, rules: [{featureType, elementType, stylers: [{color: '#99d7fe'}]}], baseMapTypeId}`: each becomes a `google.maps.StyledMapType` registered under its name (`baseMapTypeId` `roadmap` by default) |
| `google` | `zoomStyles` | `{default: <style name>, below: {4: <name>, 7: <name>}}`: the map type shown, `below` giving the style shown under each zoom (the first stop above the current zoom wins); without `below`, `default` is applied once |
| `google` | `map` | any other `google.maps.MapOptions`, as JSON, spread over the driver's `center`, `zoom` and `disableDefaultUI` |
| `openstreetmap` | `map` | Leaflet's map options, spread over `zoomControl` and `attributionControl` |
| `maplibre` | `map` | MapLibre's map options, spread over the driver's own, and part of the picture's fingerprint |

Google's Static Maps also reads `styles`: the rules of the `zoomStyles.default` style (the first
style when there is no default) are sent as `style=` parameters ([Images](usage-static.md)).

## Printing it

`MapHtml::live(string $id, array $description): string` prints
`<div id="..." data-omnimap="map" data-omnimap-description="...">`: the description as JSON
(slashes and Unicode unescaped), HTML-escaped into the attribute. The element is empty: give it a
height in CSS. `mountAll()` then draws every such element under a root
([The browser](javascript.md#omnimapjs---a-live-map)).

In Symfony and Laravel, the Twig function `omnimap(view)` prints it (or the cached picture, see
[The map cache](cache.md#the-page-mapprinter)).

## Consent

A live map loads a third party's script and tiles from the visitor's browser: the hosts are in
`capabilities()->browserHosts` (for a consent banner and a Content-Security-Policy), and the
driver runs only through the `consent(feature, load)` gate given to `mountAll()`, with the
description's `consent` as feature. Without a gate, the map loads at once. omnibase/consent's
`Consent.use('MAP', {}, load)` is one such gate. A page that must reach no third party at all
shows the cached picture instead ([The map cache](cache.md)).

## The fingerprint

`fingerprint(MapView)` is the text a picture of the view is named after, once signed. What each
provider puts in it:

| Provider | Fingerprint |
|---|---|
| `google` | `google|` + JSON of centre, zoom, markers, controls and the view's `google` options; with `legacy_signature`, glitchr/ux-google's own text instead (zoom, centre, controls; no markers, no styles) |
| `openstreetmap` | `openstreetmap|` + JSON of the tile URL template, centre, zoom, markers, controls |
| `maplibre` | `maplibre|` + JSON of the style URL, centre, zoom, markers, controls and the view's `maplibre` options |
| `test` (`TestTiles`) | `test|` + JSON of centre, zoom, markers |

The view's `id` is in none: the same view printed in two elements has one picture.
