<?php

namespace Omnimap\Tests;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Omnimap\Exception\InvalidArgumentException;
use Omnimap\Snapshot\DirectoryStorage;
use Omnimap\Snapshot\FlysystemStorage;
use Omnimap\Snapshot\Snapshot;
use Omnimap\Snapshot\SnapshotStore;
use Omnimap\Snapshot\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SnapshotStoreTest extends TestCase
{
    /** What glitchr/ux-google's GmController wrote beside a capture of 3024 x 1875 cut in squares of 512 (lesringostars, 2026-10-14). */
    private const UX_GOOGLE_METADATA = 'a:6:{s:6:"status";s:2:"OK";s:11:"image_width";i:3024;s:12:"image_height";i:1875;s:14:"image_tilesize";s:3:"512";s:12:"image_xtiles";d:6;s:12:"image_ytiles";d:4;}';

    public function testACaptureOfUxGoogleIsReadAsItIs(): void
    {
        $storage = new DirectoryStorage($dir = self::directory());
        $storage->write('google/tcIj2DZnhR2xGsZ8K8t2ID4PKVg/metadata.txt', self::UX_GOOGLE_METADATA);
        $storage->write('google/tcIj2DZnhR2xGsZ8K8t2ID4PKVg/image-23.png', 'png');
        $store = new SnapshotStore($storage, 'google', '{signature}/image-{id}', 'png');

        self::assertEquals(new Snapshot('tcIj2DZnhR2xGsZ8K8t2ID4PKVg', 3024, 1875, 512, 6, 4), $store->find('tcIj2DZnhR2xGsZ8K8t2ID4PKVg'));
        self::assertSame('png', $store->tile('tcIj2DZnhR2xGsZ8K8t2ID4PKVg', 23));
        self::assertNull($store->tile('tcIj2DZnhR2xGsZ8K8t2ID4PKVg', 24));
        self::assertSame(self::UX_GOOGLE_METADATA, serialize($store->find('tcIj2DZnhR2xGsZ8K8t2ID4PKVg')?->metadata()), 'written back byte for byte');
        self::assertNull($store->find('Ox6BPalM3_UxSEuXKIki3g1IYQs'));
        self::remove($dir);
    }

    /** @return iterable<string, array{\Closure(): Storage}> */
    public static function storages(): iterable
    {
        yield 'a directory' => [static fn () => new DirectoryStorage(self::directory())];
        yield 'Flysystem' => [static fn () => new FlysystemStorage(new Filesystem(new InMemoryFilesystemAdapter()))];
    }

    /** @param \Closure(): Storage $storage */
    #[DataProvider('storages')]
    public function testAPictureIsCutKeptAndDeleted(\Closure $storage): void
    {
        $storage = $storage();
        $store = new SnapshotStore($storage, 'google', '{signature}/image-{id}');
        $snapshot = $store->save('abc_DEF-123', self::png(1100, 600));

        self::assertEquals(new Snapshot('abc_DEF-123', 1100, 600, 512, 3, 2), $snapshot);
        self::assertSame(self::metadata(1100, 600, '512', 3.0, 2.0), $storage->read('google/abc_DEF-123/metadata.txt'));
        for ($id = 0; $id < 6; ++$id) {
            self::assertSame([512, 512], \array_slice((array) getimagesizefromstring((string) $store->tile('abc_DEF-123', $id)), 0, 2), "tile $id: a whole square, padded");
        }
        $last = imagecreatefromstring((string) $store->tile('abc_DEF-123', 5));
        self::assertNotFalse($last);
        self::assertSame(127, (imagecolorat($last, 511, 511) >> 24) & 0x7F, 'the padding is transparent');
        self::assertSame(0, (imagecolorat($last, 10, 10) >> 24) & 0x7F, 'the picture is not');

        $store->delete('abc_DEF-123');
        self::assertNull($store->find('abc_DEF-123'));
        self::assertFalse($storage->exists('google/abc_DEF-123/image-0.png'));
    }

    public function testAWholePicture(): void
    {
        $store = new SnapshotStore(new FlysystemStorage(new Filesystem(new InMemoryFilesystemAdapter())), '', 'map-{signature}-{id}', 'jpeg');
        $snapshot = $store->save('x', self::png(300, 200), null);

        self::assertEquals(new Snapshot('x', 300, 200, null, 1, 1), $snapshot);
        self::assertSame('map-x-0.jpeg', $store->tilePath('x', 0));
        self::assertSame(IMAGETYPE_JPEG, getimagesizefromstring((string) $store->tile('x', 0))[2] ?? null);
    }

    public function testOnlyASignatureReachesAPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new SnapshotStore(new DirectoryStorage('/tmp')))->find('../etc');
    }

    public function testNotAnImage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new SnapshotStore(new DirectoryStorage(self::directory())))->save('x', 'not an image');
    }

    private static function metadata(int $width, int $height, string $tilesize, float $x, float $y): string
    {
        return serialize(['status' => 'OK', 'image_width' => $width, 'image_height' => $height, 'image_tilesize' => $tilesize, 'image_xtiles' => $x, 'image_ytiles' => $y]);
    }

    public static function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 153, 215, 254));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private static function directory(): string
    {
        return sys_get_temp_dir().'/omnimap-test-'.bin2hex(random_bytes(4));
    }

    private static function remove(string $dir): void
    {
        (new DirectoryStorage(\dirname($dir)))->deleteDirectory(basename($dir));
    }
}
