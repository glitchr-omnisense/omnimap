<?php

namespace Omnimap\Tests;

use Omnimap\MapHtml;
use Omnimap\Snapshot\Snapshot;
use PHPUnit\Framework\TestCase;

final class MapHtmlTest extends TestCase
{
    public function testThePictureOfAMapWithAHostsOwnNames(): void
    {
        $html = (new MapHtml(['class' => 'google-tilemap', 'suffix' => '_html2canvas', 'src' => '/google/{signature}/{id}.webp', 'missing' => '/bundles/google/images/no-image.png']))
            ->snapshot('myMap', 'tcIj2DZnhR2xGsZ8K8t2ID4PKVg', new Snapshot('tcIj2DZnhR2xGsZ8K8t2ID4PKVg', 3024, 1875, 512, 6, 4));

        self::assertSame("<div id=\"myMap\">\n<div class=\"google-tilemap\" id=\"myMap_html2canvas\" data-omnimap=\"snapshot\" data-src=\"/google/%7Bsignature%7D/%7Bid%7D.webp\" data-signature=\"tcIj2DZnhR2xGsZ8K8t2ID4PKVg\" data-tilesize=\"512\" data-xtiles=\"6\" data-ytiles=\"4\" data-missing=\"/bundles/google/images/no-image.png\" width=\"3024\" height=\"1875\"></div>\n</div>\n", $html);
    }

    public function testNoPictureYetAndAnAttribution(): void
    {
        $html = (new MapHtml())->snapshot('map', 'abc', null, '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors');

        self::assertStringContainsString('data-tilesize="" data-xtiles="0" data-ytiles="0"', $html);
        self::assertStringContainsString('<div class="omnimap-attribution" style="', $html);
        self::assertStringContainsString('>&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors</div>', $html);
    }

    public function testALiveMapAndTheButtons(): void
    {
        $html = new MapHtml();

        self::assertSame('<div id="map" data-omnimap="map" data-omnimap-description="{&quot;provider&quot;:&quot;test&quot;,&quot;q&quot;:&quot;&lt;&#039;&quot;}"></div>'."\n", $html->live('map', ['provider' => 'test', 'q' => "<'"]));
        self::assertSame('<button class="item" type="button" data-omnimap-export="/google/maps/abc/export" data-omnimap-target="myMap" data-omnimap-token="t">O</button>', $html->button('export', 'myMap', '/google/maps/abc/export', 't', 'O', ['class' => 'item']));
        self::assertStringContainsString('data-omnimap-suppress="/s"', $html->button('suppress', 'myMap', '/s', 't', 'X'));
    }
}
