<?php

namespace Omnimap\Tests;

use Omnimap\Model\LatLng;
use Omnimap\Model\MapView;
use Omnimap\Model\Marker;
use Omnimap\Model\Size;
use Omnimap\Signer;
use Omnimap\Snapshot\DirectoryStorage;
use Omnimap\Snapshot\Snapshots;
use Omnimap\Snapshot\SnapshotStore;
use Omnimap\Testing\PlainTiles;
use Omnimap\Testing\RecordedHttpClient;
use Omnimap\Testing\TestTiles;
use PHPUnit\Framework\TestCase;

final class SnapshotsTest extends TestCase
{
    public function testTheCacheIsTheSameWhicheverMakesThePicture(): void
    {
        $tiles = new TestTiles(new RecordedHttpClient(PlainTiles::answer()));
        $dir = sys_get_temp_dir().'/omnimap-snapshots-'.bin2hex(random_bytes(4));
        $store = new SnapshotStore(new DirectoryStorage($dir), 'google');
        $view = new MapView(new LatLng(35, 135), 6);

        $browser = new Snapshots($tiles, new Signer('c2VjcmV0'), $store);
        $server = new Snapshots($tiles, new Signer('c2VjcmV0'), $store, $tiles, new Size(1000, 600));

        self::assertSame((new Signer('c2VjcmV0'))->sign($tiles->fingerprint($view)), $browser->signature($view));
        self::assertNotSame($browser->signature($view), $browser->signature($view->withMarker(new Marker(new LatLng(34, 135)))), 'this provider names its markers');
        self::assertFalse($browser->canMake());
        self::assertNull($browser->findOrMake($view));

        $made = $server->findOrMake($view);
        self::assertSame([1000, 600, 2, 2], [$made?->width, $made?->height, $made?->xtiles, $made?->ytiles]);
        self::assertEquals($made, $browser->find($view), 'the browser\'s side reads what the server made');

        $png = SnapshotStoreTest::png(2000, 1000);
        $captured = $browser->save($browser->signature($view), 'data:image/png;base64,'.base64_encode($png));
        self::assertSame([2000, 1000, 4, 2], [$captured->width, $captured->height, $captured->xtiles, $captured->ytiles]);

        $browser->delete($browser->signature($view));
        self::assertNull($server->find($view));
        @rmdir($dir.'/google');
        @rmdir($dir);
    }
}
