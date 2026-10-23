<?php

namespace Omnimap\Model;

use Omnimap\Exception\InvalidArgumentException;

/**
 * What a map shows, whoever draws it: its centre and zoom (the Web Mercator
 * zoom every provider shares: 0 is the whole world in one 256-pixel tile),
 * its markers, whether the provider's own controls are shown, and the
 * options only one provider understands, under that provider's name
 * (['google' => ['styles' => [...]]]): another provider ignores them.
 *
 * The id is the page element's the map is drawn into.
 */
final readonly class MapView implements \JsonSerializable
{
    /**
     * @param list<Marker>                        $markers
     * @param array<string, array<string, mixed>> $options
     */
    public function __construct(
        public LatLng $center,
        public int $zoom,
        public array $markers = [],
        public bool $controls = true,
        public array $options = [],
        public string $id = 'map',
    ) {
        if ($zoom < 0 || $zoom > 24) {
            throw new InvalidArgumentException(\sprintf('A zoom lies between 0 and 24, not %d.', $zoom));
        }
    }

    public function withMarker(Marker $marker): self
    {
        return new self($this->center, $this->zoom, [...$this->markers, $marker], $this->controls, $this->options, $this->id);
    }

    /** @param list<Marker> $markers */
    public function withMarkers(array $markers): self
    {
        return new self($this->center, $this->zoom, $markers, $this->controls, $this->options, $this->id);
    }

    /** @param array<string, mixed> $options merged over the provider's options already set */
    public function withOptions(string $provider, array $options): self
    {
        return new self($this->center, $this->zoom, $this->markers, $this->controls, [$provider => array_replace($this->options[$provider] ?? [], $options)] + $this->options, $this->id);
    }

    /** @return array<string, mixed> the options of one provider */
    public function options(string $provider): array
    {
        return $this->options[$provider] ?? [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'center' => $this->center->toArray(),
            'zoom' => $this->zoom,
            'markers' => array_map(static fn (Marker $marker) => $marker->toArray(), $this->markers),
            'controls' => $this->controls,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
