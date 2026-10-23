<?php

namespace Omnimap;

/**
 * One map provider, configured: Google, OpenStreetMap... Its Capabilities
 * say what it can be asked - draw a map in the browser
 * (MapRendererInterface), make an image of one on the server
 * (StaticMapInterface), find places (GeocoderInterface), give tiles
 * (TileSourceInterface) - and what it costs the visitor's privacy.
 */
interface ProviderInterface
{
    /** The factory's name: "google", "openstreetmap"... */
    public function getName(): string;

    public function getTitle(): string;

    public function capabilities(): Capabilities;
}
