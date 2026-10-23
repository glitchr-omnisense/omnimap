<?php

namespace Omnimap\Tests;

use Omnimap\Capabilities;
use Omnimap\Config;
use Omnimap\Exception\InvalidConfigException;
use Omnimap\Exception\NotSupportedException;
use Omnimap\ProviderFactory;
use Omnimap\ProviderInterface;
use Omnimap\Registry;
use Omnimap\Testing\PlainTiles;
use Omnimap\Testing\RecordedHttpClient;
use Omnimap\Testing\TestTiles;
use PHPUnit\Framework\TestCase;

final class RegistryTest extends TestCase
{
    public function testProvidersByNameEachBuiltOnce(): void
    {
        $registry = new Registry([self::factory()], ['maps' => ['factory' => 'test', 'options' => ['user_agent' => 'x']]]);

        self::assertSame($registry->get('maps'), $registry->renderer('maps'));
        self::assertSame($registry->get('maps'), $registry->staticMap('maps'));
        self::assertSame($registry->get('maps'), $registry->tiles('maps'));
        self::assertSame(['maps'], $registry->names());
        self::assertSame(['test'], $registry->factories());
        self::assertSame(['user_agent' => 'x'], $registry->options('maps'));
        self::assertSame(['renderer', 'staticMap', 'tiles'], $registry->get('maps')->capabilities()->contracts());

        try {
            $registry->geocoder('maps');
            self::fail('Not a geocoder.');
        } catch (NotSupportedException $e) {
            self::assertStringContainsString('does not geocode', $e->getMessage());
        }
        $this->expectException(InvalidConfigException::class);
        $registry->get('places');
    }

    public function testARequiredOption(): void
    {
        $this->expectExceptionMessage('The "test" provider needs: user_agent.');
        (new Registry([self::factory()], ['maps' => ['factory' => 'test']]))->get('maps');
    }

    public function testConfigReadsWhatAnEnvironmentGives(): void
    {
        $config = new Config(['a' => 'yes', 'b' => '0', 'c' => '12', 'd' => '']);

        self::assertSame([true, false, 12, null, 'fallback'], [$config->bool('a'), $config->bool('b'), $config->int('c'), $config->string('d'), $config->defaults(['d2' => 'fallback'])->get('d2')]);
        self::assertSame(['contracts' => ['geocoder'], 'renderer' => false, 'staticMap' => false, 'geocoder' => true, 'tiles' => false, 'browserHosts' => [], 'cookies' => false, 'terms' => []], (new Capabilities(geocoder: true))->toArray());
    }

    private static function factory(): ProviderFactory
    {
        return new class extends ProviderFactory {
            protected function populate(Config $c): void
            {
                $c->defaults(['omnimap.factory_name' => 'test', 'omnimap.factory_title' => 'Test', 'omnimap.required_options' => ['user_agent']]);
            }

            protected function build(Config $c): ProviderInterface
            {
                return new TestTiles(new RecordedHttpClient(PlainTiles::answer()));
            }
        };
    }
}
