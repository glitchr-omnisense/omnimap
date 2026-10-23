<?php

namespace Omnimap;

use Omnimap\Model\MapView;

/**
 * The map in the browser: the provider turns a view into a description the
 * page's script draws (assets/omnimap.js and the provider's driver) - plain
 * data, no code, the same for a Twig page, a Laravel view or a React
 * component:
 *
 *   {"provider": "google", "view": {...MapView::toArray()}, "library": {...what the driver loads}, "options": {...}}
 *
 * A renderer also says what a picture of the view depends on
 * (fingerprint()): a captured image of the map (Snapshot) is named by it,
 * signed, so a change of centre, zoom or provider makes a new one.
 */
interface MapRendererInterface extends ProviderInterface
{
    /** @return array<string, mixed> JSON-ready */
    public function describe(MapView $view): array;

    /** The text a picture of this view is named after, once signed (Signer). */
    public function fingerprint(MapView $view): string;

    /** The attribution the map must show, as HTML (the provider's terms ask for it). */
    public function attribution(): string;
}
