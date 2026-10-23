<?php

namespace Omnimap\Tests;

use PHPUnit\Framework\TestCase;

/** docs/installation.md's PHP block, run as it is written (its autoloader the one of this run). */
final class DocsTest extends TestCase
{
    public function testTheInstallationPageRunsAsWritten(): void
    {
        $text = (string) file_get_contents(__DIR__.'/../docs/installation.md');
        self::assertSame(1, preg_match('~```php\n(.*?)```~s', $text, $block));
        // The autoloader that knows omnimap (not PHPUnit's own, should PHPUnit come as a PHAR).
        $autoload = null;
        foreach (\Composer\Autoload\ClassLoader::getRegisteredLoaders() as $vendor => $loader) {
            if ($loader->findFile(\Omnimap\Testing\TestTiles::class)) {
                $autoload = $vendor.'/autoload.php';
                break;
            }
        }
        if (null === $autoload) {
            self::markTestSkipped('No autoloader knows omnimap.');
        }
        $code = str_replace("require 'vendor/autoload.php';", 'require '.var_export($autoload, true).';', $block[1], $replaced);
        self::assertSame(1, $replaced);
        $script = (string) tempnam(sys_get_temp_dir(), 'omnimap-docs');
        file_put_contents($script, $code);
        try {
            $process = proc_open([\PHP_BINARY, $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
            $out = (string) stream_get_contents($pipes[1]);
            $err = (string) stream_get_contents($pipes[2]);
            self::assertSame(0, proc_close($process), $err.$out);
        } finally {
            unlink($script);
        }

        $lines = ['1512x938 in 3x2 tiles of 512', '1 frame', 'Kyoto, Japan'];
        self::assertSame(implode("\n", $lines)."\n", $out);
        foreach ($lines as $line) {
            self::assertStringContainsString("\n// $line\n", $block[1], 'the page says what it prints');
        }
    }
}
