<?php

namespace Omnimap\Tests;

use Omnimap\MapHtml;
use Omnimap\MapPrinter;
use Omnimap\Model\LatLng;
use Omnimap\Model\MapView;
use Omnimap\Signer;
use Omnimap\Snapshot\DirectoryStorage;
use Omnimap\Snapshot\Snapshots;
use Omnimap\Snapshot\SnapshotStore;
use Omnimap\Testing\PlainTiles;
use Omnimap\Testing\RecordedHttpClient;
use Omnimap\Testing\TestTiles;
use PHPUnit\Framework\TestCase;

final class MapPrinterTest extends TestCase
{
    /** ux-google's rule: cache_only, the live map for whoever may change it until a picture is kept. */
    public function testThePictureTheLiveMapOrAnEmptyFrame(): void
    {
        $tiles = new TestTiles(new RecordedHttpClient(PlainTiles::answer()));
        $store = new SnapshotStore(new DirectoryStorage($dir = sys_get_temp_dir().'/omnimap-printer-'.bin2hex(random_bytes(4))));
        $snapshots = new Snapshots($tiles, new Signer('c2VjcmV0'), $store);
        $printer = new MapPrinter($snapshots);
        $view = new MapView(new LatLng(35, 135), 6, id: 'myMap');

        self::assertStringContainsString('data-omnimap="snapshot"', $printer->map($view), 'a visitor: the empty frame');
        self::assertStringContainsString('data-xtiles="0"', $printer->map($view));
        self::assertStringContainsString('data-omnimap="map"', $printer->map($view, true), 'whoever may change it: the live map');
        self::assertStringContainsString('data-omnimap="map"', (new MapPrinter($snapshots, new MapHtml(), false))->map($view), 'not cache_only: the live map for everyone');
        self::assertSame('', $printer->suppressButton($view, true, '/s', 't', 'X'), 'nothing to suppress yet');
        self::assertStringContainsString('data-omnimap-export="/e"', $printer->exportButton($view, true, '/e', 't', 'O'));
        self::assertSame('', $printer->exportButton($view, false, '/e', 't', 'O'));

        $snapshots->save($snapshots->signature($view), SnapshotStoreTest::png(600, 400));
        self::assertStringContainsString('data-xtiles="2"', $printer->map($view, true), 'a picture: shown to everyone');
        self::assertStringContainsString('data-omnimap-suppress="/s"', $printer->suppressButton($view, true, '/s', 't', 'X'));
        self::assertSame('', $printer->exportButton($view, true, '/e', 't', 'O'), 'the picture is what they see: nothing to export');
        $snapshots->delete($snapshots->signature($view));
        @rmdir($dir);
    }

    public function testAPictureTheServerCouldNotMake(): void
    {
        $tiles = new TestTiles(new RecordedHttpClient(static fn () => [503, '']));
        $errors = [];
        $printer = new MapPrinter(new Snapshots($tiles, new Signer(''), new SnapshotStore(new DirectoryStorage(sys_get_temp_dir().'/omnimap-none')), $tiles), onError: static function (\Throwable $e) use (&$errors): void {
            $errors[] = $e->getMessage();
        });

        self::assertStringContainsString('data-xtiles="0"', $printer->map(new MapView(new LatLng(0, 0), 2)));
        self::assertCount(1, $errors);
        self::assertStringContainsString('503', $errors[0]);
    }
}
