<?php

namespace Omnimap\Model;

/** A position in pixels: on the whole world at a zoom (Projection\WebMercator), or on an image. */
final readonly class Point
{
    public function __construct(public float $x, public float $y)
    {
    }
}
