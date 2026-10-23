<?php

namespace Omnimap\Model;

/** An image a provider made: its bytes, its media type and its size. */
final readonly class Image
{
    public function __construct(
        public string $bytes,
        public string $mimeType,
        public Size $size,
    ) {
    }
}
