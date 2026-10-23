<?php

namespace Omnimap\Snapshot;

use Omnimap\Exception\InvalidArgumentException;

/**
 * The map pictures in a Storage, laid out as glitchr/ux-google laid them
 * out: under $directory ("google"), one folder per signature, the tiles
 * named by $pattern ("{signature}/image-{id}") with the format's extension,
 * the metadata beside them:
 *
 *   google/tcIj2DZnhR2xGsZ8K8t2ID4PKVg/image-0.png ... image-23.png
 *   google/tcIj2DZnhR2xGsZ8K8t2ID4PKVg/metadata.txt
 *
 * save() cuts an image into tiles (GD) - a picture the browser captured, or
 * one the server made (Snapshots::make()). What has been asked during this
 * object's life is remembered (one storage round trip per question: on S3
 * each is an HTTP request); save() and delete() forget it.
 */
final class SnapshotStore
{
    /** @var array<string, ?Snapshot> */
    private array $found = [];

    public function __construct(
        private readonly Storage $storage,
        private readonly string $directory = 'omnimap',
        private readonly string $pattern = '{signature}/image-{id}',
        private readonly string $format = 'png',
    ) {
        if (!\in_array($format, ['png', 'jpeg', 'jpg', 'webp'], true)) {
            throw new InvalidArgumentException(\sprintf('A map picture is kept as png, jpeg or webp, not "%s".', $format));
        }
    }

    public function find(string $signature): ?Snapshot
    {
        self::check($signature);
        if (\array_key_exists($signature, $this->found)) {
            return $this->found[$signature];
        }
        $contents = $this->storage->read($this->metadataPath($signature));
        $metadata = null === $contents ? false : @unserialize(trim($contents), ['allowed_classes' => false]);

        return $this->found[$signature] = \is_array($metadata) ? Snapshot::fromMetadata($signature, $metadata) : null;
    }

    /** A tile's bytes, in the store's format; null when there is none. */
    public function tile(string $signature, int $id): ?string
    {
        self::check($signature);

        return $id < 0 ? null : $this->storage->read($this->tilePath($signature, $id));
    }

    /**
     * Keeps an image as the picture of $signature: cut into squares of
     * $tilesize (the last row and column padded with transparency), or
     * whole when $tilesize is null. Replaces what was kept under that name.
     */
    public function save(string $signature, string $image, ?int $tilesize = 512): Snapshot
    {
        self::check($signature);
        $picture = @imagecreatefromstring($image);
        if (false === $picture) {
            throw new InvalidArgumentException('The map picture is not an image GD can read.');
        }
        $width = imagesx($picture);
        $height = imagesy($picture);
        $this->delete($signature);

        if (null === $tilesize || $tilesize < 1) {
            $this->storage->write($this->tilePath($signature, 0), $this->encode($picture));
            $snapshot = new Snapshot($signature, $width, $height, null, 1, 1);
        } else {
            $xtiles = (int) ceil($width / $tilesize);
            $ytiles = (int) ceil($height / $tilesize);
            for ($y = 0; $y < $ytiles; ++$y) {
                for ($x = 0; $x < $xtiles; ++$x) {
                    $tile = imagecreatetruecolor($tilesize, $tilesize);
                    imagealphablending($tile, false);
                    imagesavealpha($tile, true);
                    imagefill($tile, 0, 0, (int) imagecolorallocatealpha($tile, 0, 0, 0, 127));
                    $w = min($tilesize, $width - $x * $tilesize);
                    $h = min($tilesize, $height - $y * $tilesize);
                    imagecopy($tile, $picture, 0, 0, $x * $tilesize, $y * $tilesize, $w, $h);
                    $this->storage->write($this->tilePath($signature, $y * $xtiles + $x), $this->encode($tile));
                }
            }
            $snapshot = new Snapshot($signature, $width, $height, $tilesize, $xtiles, $ytiles);
        }
        $this->storage->write($this->metadataPath($signature), serialize($snapshot->metadata()));

        return $this->found[$signature] = $snapshot;
    }

    /** Forgets the picture of $signature, tiles and metadata. */
    public function delete(string $signature): void
    {
        self::check($signature);
        $this->storage->deleteDirectory($this->folder($signature));
        unset($this->found[$signature]);
    }

    public function format(): string
    {
        return $this->format;
    }

    public function tilePath(string $signature, int $id): string
    {
        return $this->prefix().str_replace(['{signature}', '{id}'], [$signature, (string) $id], $this->pattern).'.'.$this->format;
    }

    public function metadataPath(string $signature): string
    {
        return $this->folder($signature).'/metadata.txt';
    }

    private function folder(string $signature): string
    {
        return $this->prefix().$signature;
    }

    private function prefix(): string
    {
        return '' === trim($this->directory, '/') ? '' : trim($this->directory, '/').'/';
    }

    private function encode(\GdImage $image): string
    {
        ob_start();
        match ($this->format) {
            'jpeg', 'jpg' => imagejpeg($image, null, 92),
            'webp' => imagewebp($image, null, 90),
            default => imagepng($image),
        };

        return (string) ob_get_clean();
    }

    /** A signature is URL-safe base64: nothing else reaches the storage's paths. */
    private static function check(string $signature): void
    {
        if (!preg_match('~^[A-Za-z0-9_-]{1,128}$~', $signature)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not a map signature.', $signature));
        }
    }
}
