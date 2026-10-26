<?php

namespace Omnimap\Tests;

use Omnimap\MapRendererInterface;
use Omnimap\Model\LatLng;
use Omnimap\Model\MapView;
use Omnimap\Model\Marker;
use Omnimap\ProviderFactoryInterface;
use Omnimap\Testing\TestTiles;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * schema/map-description.schema.json is what describe() gives and the drivers read: every renderer's
 * description holds to it - the core's test provider, and each provider package installed beside the
 * core (the harness installs them all).
 */
final class MapDescriptionSchemaTest extends TestCase
{
    /** @return iterable<string, array{\Closure(): MapRendererInterface}> */
    public static function renderers(): iterable
    {
        yield 'test' => [static fn (): MapRendererInterface => new TestTiles(new MockHttpClient())];
        /** @var array<string, array{0: string, 1: array<string, string>}> $providers the provider packages, when installed beside the core */
        $providers = [
            'openstreetmap' => ['Omnimap\OpenStreetMap\OpenStreetMapFactory', []],
            'google' => ['Omnimap\Google\GoogleFactory', ['api_key' => 'browser-key']],
            'maplibre' => ['Omnimap\MapLibre\MapLibreFactory', []],
        ];
        foreach ($providers as $name => [$factory, $options]) {
            if (class_exists($factory)) {
                yield $name => [static function () use ($factory, $options): MapRendererInterface {
                    $instance = new $factory();
                    \assert($instance instanceof ProviderFactoryInterface);
                    $provider = $instance->create($options);
                    \assert($provider instanceof MapRendererInterface);

                    return $provider;
                }];
            }
        }
    }

    /** @param \Closure(): MapRendererInterface $renderer */
    #[DataProvider('renderers')]
    public function testADescriptionHoldsToTheSchema(\Closure $renderer): void
    {
        $view = new MapView(new LatLng(35.0116, 135.7681), 12, [new Marker(new LatLng(35.0036, 135.7780), 'Gion'), new Marker(new LatLng(34.9671, 135.7727), null, '#0055aa')], false, id: 'kyoto');
        $description = json_decode((string) json_encode($renderer()->describe($view), \JSON_THROW_ON_ERROR));

        $result = self::validator()->validate($description, self::SCHEMA_ID);
        self::assertTrue($result->isValid(), (string) json_encode($result->error()?->args()).' '.$result->error()?->message());
    }

    public function testWhatIsNotADescriptionIsRefused(): void
    {
        foreach ([
            '{"consent": "MAP", "view": {"id": "m", "center": {"lat": 0, "lng": 0}, "zoom": 2, "markers": [], "controls": true}, "attribution": ""}',
            '{"provider": "x", "consent": "MAP", "view": {"id": "m", "center": {"lat": 91, "lng": 0}, "zoom": 2, "markers": [], "controls": true}, "attribution": ""}',
            '{"provider": "x", "consent": "MAP", "view": {"id": "m", "center": {"lat": 0, "lng": 0}, "zoom": 25, "markers": [], "controls": true}, "attribution": ""}',
        ] as $json) {
            self::assertFalse(self::validator()->validate(json_decode($json), self::SCHEMA_ID)->isValid(), $json);
        }
    }

    private const SCHEMA_ID = 'https://raw.githubusercontent.com/glitchr-omnisense/omnimap/1.x/schema/map-description.schema.json';

    private static function validator(): Validator
    {
        $validator = new Validator();
        $validator->resolver()?->registerFile(self::SCHEMA_ID, \dirname(__DIR__).'/schema/map-description.schema.json');

        return $validator;
    }
}
