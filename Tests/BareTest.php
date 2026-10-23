<?php

namespace Omnimap\Tests;

use Omnimap\Registry;
use PHPUnit\Framework\TestCase;

/**
 * omniscient/omnimap with no framework: in PHP processes of their own (this
 * one has loaded PHPUnit), the harness's bare script describes a view,
 * makes its picture of tiles, signs it, keeps it in a directory, prints it,
 * deletes it and geocodes - and every class of the package is loaded.
 * Nothing may come from outside the package but what it requires: the PSR
 * cache interface, Symfony's HTTP client contracts (interfaces only) and
 * geocoder-php - no framework, no HTTP client, no Flysystem nor omnidrive (suggested), no
 * application, even in the harness where they are installed beside it.
 */
final class BareTest extends TestCase
{
    private const FOREIGN = '~^(?:Symfony\\\\(?!Contracts\\\\HttpClient\\\\|Polyfill\\\\)|(?:Illuminate|Laravel|Doctrine|Twig|League|GuzzleHttp|Nyholm|Http|Omnidrive|Omnibridge|Base|App)\\\\)~';
    private const ALLOWED_FILES = '~/vendor/(?:psr/simple-cache|symfony/http-client-contracts|willdurand/geocoder|composer)/|/vendor/autoload\.php$~';

    public function testAViewMadeCachedAndGeocodedByHandLoadsNothingButOmnimapAndWhatItRequires(): void
    {
        $report = self::php([__DIR__.'/../docker/harness/bin/bare']);

        self::assertSame('test', $report['description']['provider']);
        self::assertSame(['lat' => 35.0116, 'lng' => 135.7681], $report['description']['view']['center']);
        self::assertMatchesRegularExpression('~^[A-Za-z0-9_-]{27}$~', $report['signature']);
        self::assertFalse($report['before']);
        self::assertSame(['signature' => $report['signature'], 'width' => 1512, 'height' => 938, 'tilesize' => 512, 'xtiles' => 3, 'ytiles' => 2], $report['snapshot']);
        self::assertSame(35, $report['tile-requests'], 'the 7 x 5 tiles of a 1512 x 938 view, once');
        self::assertSame('omnimap-tests/1.0 (+https://github.com/glitchr-studio/omnimap)', $report['user-agent']);
        self::assertContains('maps/'.$report['signature'].'/metadata.txt', $report['files']);
        self::assertContains('maps/'.$report['signature'].'/image-5.png', $report['files']);
        self::assertSame('width="512" height="512"', $report['tile-0']);
        self::assertSame(35, $report['tile-requests-after-remake'], 'made again from the cached tiles');
        self::assertStringContainsString('data-signature="'.$report['signature'].'"', $report['html']);
        self::assertStringContainsString('&copy; Test tiles', $report['html']);
        self::assertFalse($report['after-delete']);
        self::assertSame([35.0115754, 135.7681441], [$report['geocode'][0]['position']['lat'], $report['geocode'][0]['position']['lng']]);
        self::assertSame(['Kyoto, Japan', 1], [$report['geocode'][1], $report['geocode'][2]], 'the second answer from the cache');

        self::assertContains('Omnimap\Snapshot\Snapshots', $report['loaded']['symbols']);
        self::assertSame([], self::foreign($report['loaded']['symbols']), 'no class of a framework, an HTTP client, Flysystem or an application');
        self::assertSame([], self::outside($report['loaded']['files'], $report['loaded']['eager']), 'no file but the package and what it requires');
    }

    public function testEveryClassLoadsWithTheRequiredPackagesAlone(): void
    {
        $report = self::php([__DIR__.'/Fixtures/load-all.php', self::autoload(), \dirname(__DIR__)]);

        self::assertGreaterThan(30, \count($report['loaded']));
        self::assertContains('Omnimap\Static\TileStitcher', $report['loaded']);
        self::assertNotContains('Omnimap\Snapshot\FlysystemStorage', $report['loaded'], 'over a suggested package: left out');
        self::assertSame([], self::foreign($report['symbols']));
        self::assertSame([], self::outside($report['files'], $report['eager']));
    }

    /** The check is not blind: a framework's class, once loaded, is seen. */
    public function testTheReportSeesWhatIsLoaded(): void
    {
        if (!class_exists(\League\Flysystem\Filesystem::class)) {
            self::markTestSkipped('league/flysystem is not installed.');
        }
        $report = self::php([__DIR__.'/Fixtures/load-all.php', self::autoload(), \dirname(__DIR__), \League\Flysystem\Filesystem::class]);

        self::assertContains(\League\Flysystem\Filesystem::class, self::foreign($report['symbols']));
        self::assertNotEmpty(preg_grep('~/league/flysystem/~', self::outside($report['files'], $report['eager'])));
    }

    /** @param list<string> $symbols @return list<string> */
    private static function foreign(array $symbols): array
    {
        return array_values(preg_grep(self::FOREIGN, $symbols) ?: []);
    }

    /**
     * The files loaded from outside the package but those allowed - and those
     * Composer includes for every package at start (autoload "files").
     *
     * @param list<?string> $files
     * @param list<?string> $eager
     *
     * @return list<string>
     */
    private static function outside(array $files, array $eager): array
    {
        $root = realpath(\dirname(__DIR__)).'/';
        $eager = array_map('dirname', array_filter($eager));

        return array_values(array_filter($files, static fn (?string $file) => null !== $file && (!str_starts_with($file, $root) || str_starts_with($file, $root.'vendor/')) && !\in_array(\dirname($file), $eager, true) && !preg_match(self::ALLOWED_FILES, $file)));
    }

    /** The autoloader that knows omnimap and its tests. */
    private static function autoload(): string
    {
        foreach (\Composer\Autoload\ClassLoader::getRegisteredLoaders() as $vendor => $loader) {
            if ($loader->findFile(Registry::class) && $loader->findFile(self::class)) {
                return $vendor.'/autoload.php';
            }
        }
        self::markTestSkipped('No autoloader knows omnimap\'s tests.');
    }

    /**
     * @param list<string> $arguments
     *
     * @return array<string, mixed>
     */
    private static function php(array $arguments): array
    {
        $process = proc_open([\PHP_BINARY, ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['OMNIMAP_AUTOLOAD' => self::autoload()] + getenv());
        self::assertIsResource($process);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        $status = proc_close($process);
        $report = json_decode($out, true);
        self::assertSame(0, $status, $err.$out);
        self::assertIsArray($report, $err.$out);

        return $report;
    }
}
