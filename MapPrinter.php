<?php

namespace Omnimap;

use Omnimap\Model\MapView;
use Omnimap\Snapshot\Snapshot;
use Omnimap\Snapshot\Snapshots;

/**
 * What a page shows of a view, whatever the host: the picture kept in the
 * cache when there is one (made now by the server when it can), else the
 * live map - for whoever may change the map only, when $cacheOnly (the
 * visitor never loads the provider: an empty frame until a picture is
 * captured), for everyone otherwise. It is glitchr/ux-google's rule
 * (cache_only, cache_control), the provider aside.
 *
 * The host gives what it alone knows: whether the visitor may change the
 * map, and the export and suppress URLs and token (omnibridge's Twig
 * extension asks its Access, Urls and Tokens).
 */
final class MapPrinter
{
    /** @var \Closure(\Throwable): void */
    private readonly \Closure $onError;

    /** @param (\Closure(\Throwable): void)|null $onError what to do with a picture the server could not make (log it): the page shows no picture */
    public function __construct(
        private readonly Snapshots $snapshots,
        private readonly MapHtml $html = new MapHtml(),
        private readonly bool $cacheOnly = true,
        ?\Closure $onError = null,
    ) {
        $this->onError = $onError ?? static function (\Throwable $e): void {
        };
    }

    public function map(MapView $view, bool $mayChange = false): string
    {
        $snapshot = $this->snapshot($view);
        $renderer = $this->snapshots->renderer();
        if (null !== $snapshot || ($this->cacheOnly && !$mayChange)) {
            return $this->html->snapshot($view->id, $this->snapshots->signature($view), $snapshot, $renderer->attribution());
        }

        return $this->html->live($view->id, $renderer->describe($view));
    }

    /**
     * The button that sends the live map's picture to the cache: for whoever
     * may change the map, when the live map is what they see - no picture
     * kept (map() shows a kept one to everyone; suppress it first).
     *
     * @param array<string, string> $attributes
     */
    public function exportButton(MapView $view, bool $mayChange, string $url, string $token, string $contents, array $attributes = []): string
    {
        return $mayChange && null === $this->snapshots->find($view) ? $this->html->button('export', $view->id, $url, $token, $contents, $attributes) : '';
    }

    /**
     * The button that deletes the picture kept: for whoever may change the
     * map, when there is a picture.
     *
     * @param array<string, string> $attributes
     */
    public function suppressButton(MapView $view, bool $mayChange, string $url, string $token, string $contents, array $attributes = []): string
    {
        return $mayChange && null !== $this->snapshots->find($view) ? $this->html->button('suppress', $view->id, $url, $token, $contents, $attributes) : '';
    }

    public function signature(MapView $view): string
    {
        return $this->snapshots->signature($view);
    }

    public function snapshots(): Snapshots
    {
        return $this->snapshots;
    }

    private function snapshot(MapView $view): ?Snapshot
    {
        try {
            return $this->snapshots->findOrMake($view);
        } catch (\Throwable $e) {
            ($this->onError)($e);

            return null;
        }
    }
}
