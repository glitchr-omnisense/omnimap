<?php

namespace Omnimap;

use Omnimap\Model\Image;
use Omnimap\Model\MapView;
use Omnimap\Model\Size;

/**
 * A map as an image, made on the server: the provider's static map service,
 * or tiles put together. The image carries what the provider asks to be
 * shown on it (an attribution) when the provider does not draw it itself.
 */
interface StaticMapInterface extends ProviderInterface
{
    /** @param int $scale 1, or 2 for a screen of twice the density: the image is $size x $scale pixels */
    public function image(MapView $view, Size $size, int $scale = 1): Image;
}
