<?php

namespace Omnimap;

use Omnimap\Snapshot\Snapshot;

/**
 * The map's element, as any host prints it (Twig, Blade, a React server
 * render, plain PHP): either the picture kept in the cache, cut in tiles
 * that assets/snapshot.js lays out, or a live map that assets/omnimap.js
 * draws from the renderer's description.
 *
 * The picture's markup takes the host's own names where it has older ones
 * to keep (lesringostars: class "google-tilemap", id "myMap_html2canvas",
 * src "/google/{signature}/{id}.webp").
 */
final class MapHtml
{
    /** @var array{class: string, suffix: string, src: string, missing: string} */
    private array $options;

    /** @param array{class?: string, suffix?: string, src?: string, missing?: string} $options */
    public function __construct(array $options = [])
    {
        $this->options = $options + ['class' => 'omnimap-snapshot', 'suffix' => '_snapshot', 'src' => '/omnimap/{signature}/{id}.webp', 'missing' => ''];
    }

    /**
     * The picture of a map: the element $id, the tiles' frame in it, and the
     * attribution the provider asks for. No snapshot yet: the frame with no
     * tile (nothing is shown, nothing is fetched).
     */
    public function snapshot(string $id, string $signature, ?Snapshot $snapshot, string $attribution = ''): string
    {
        $frame = self::attributes([
            'class' => $this->options['class'],
            'id' => $id.$this->options['suffix'],
            'data-omnimap' => 'snapshot',
            'data-src' => str_replace(['{', '}'], ['%7B', '%7D'], $this->options['src']),
            'data-signature' => $signature,
            'data-tilesize' => null === $snapshot?->tilesize ? '' : (string) $snapshot->tilesize,
            'data-xtiles' => (string) ($snapshot->xtiles ?? 0),
            'data-ytiles' => (string) ($snapshot->ytiles ?? 0),
            'data-missing' => $this->options['missing'],
            'width' => (string) ($snapshot->width ?? 0),
            'height' => (string) ($snapshot->height ?? 0),
        ]);

        return '<div '.self::attributes(['id' => $id]).">\n<div ".$frame."></div>\n".self::attribution($attribution)."</div>\n";
    }

    /**
     * A live map: the element $id carrying the renderer's description, which
     * assets/omnimap.js draws (mountAll()) once the provider's script may be
     * loaded.
     *
     * @param array<string, mixed> $description
     */
    public function live(string $id, array $description): string
    {
        return '<div '.self::attributes(['id' => $id, 'data-omnimap' => 'map', 'data-omnimap-description' => (string) json_encode($description, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)])."></div>\n";
    }

    /**
     * A button that sends the live map's picture to the cache (assets/capture.js),
     * or that deletes the picture kept: for whoever may change the map.
     */
    /** @param array<string, string> $attributes */
    public function button(string $action, string $id, string $url, string $token, string $contents, array $attributes = []): string
    {
        return '<button '.self::attributes($attributes + [
            'type' => 'button',
            'data-omnimap-'.('suppress' === $action ? 'suppress' : 'export') => $url,
            'data-omnimap-target' => $id,
            'data-omnimap-token' => $token,
        ]).'>'.$contents.'</button>';
    }

    private static function attribution(string $attribution): string
    {
        return '' === $attribution ? '' : '<div class="omnimap-attribution" style="position:absolute;right:0;bottom:0;z-index:1;padding:0 5px;font:11px/1.5 sans-serif;color:#333;background:rgba(255,255,255,.75)">'.$attribution."</div>\n";
    }

    /** @param array<string, string> $attributes */
    private static function attributes(array $attributes): string
    {
        $html = [];
        foreach ($attributes as $name => $value) {
            $html[] = $name.'="'.htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8').'"';
        }

        return implode(' ', $html);
    }
}
