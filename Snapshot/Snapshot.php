<?php

namespace Omnimap\Snapshot;

/**
 * A picture of a map, kept: its name (the signature of what it shows), its
 * size, and the grid of square tiles it is cut into (the last row and
 * column padded to a whole tile). metadata() is the file kept beside the
 * tiles, written as glitchr/ux-google wrote it - the same keys and the same
 * types, PHP-serialized - so the captures made before omnimap are read as
 * they are.
 */
final readonly class Snapshot implements \JsonSerializable
{
    public function __construct(
        public string $signature,
        public int $width,
        public int $height,
        public ?int $tilesize,
        public int $xtiles,
        public int $ytiles,
    ) {
    }

    /** @param array<array-key, mixed> $metadata */
    public static function fromMetadata(string $signature, array $metadata): ?self
    {
        if ('OK' !== ($metadata['status'] ?? null)) {
            return null;
        }
        $tilesize = is_numeric($metadata['image_tilesize'] ?? null) ? (int) $metadata['image_tilesize'] : null;

        return new self(
            $signature,
            is_numeric($metadata['image_width'] ?? null) ? (int) $metadata['image_width'] : 0,
            is_numeric($metadata['image_height'] ?? null) ? (int) $metadata['image_height'] : 0,
            $tilesize > 0 ? $tilesize : null,
            is_numeric($metadata['image_xtiles'] ?? null) ? (int) $metadata['image_xtiles'] : 1,
            is_numeric($metadata['image_ytiles'] ?? null) ? (int) $metadata['image_ytiles'] : 1,
        );
    }

    /** @return array{status: string, image_width: int, image_height: int, image_tilesize: ?string, image_xtiles: float, image_ytiles: float} */
    public function metadata(): array
    {
        return [
            'status' => 'OK',
            'image_width' => $this->width,
            'image_height' => $this->height,
            'image_tilesize' => null === $this->tilesize ? null : (string) $this->tilesize,
            'image_xtiles' => (float) $this->xtiles,
            'image_ytiles' => (float) $this->ytiles,
        ];
    }

    public function tiles(): int
    {
        return $this->xtiles * $this->ytiles;
    }

    /** @return array{signature: string, width: int, height: int, tilesize: ?int, xtiles: int, ytiles: int} */
    public function jsonSerialize(): array
    {
        return ['signature' => $this->signature, 'width' => $this->width, 'height' => $this->height, 'tilesize' => $this->tilesize, 'xtiles' => $this->xtiles, 'ytiles' => $this->ytiles];
    }
}
