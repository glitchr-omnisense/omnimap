<?php

namespace Omnimap\Snapshot;

use Omnimap\Exception\NotSupportedException;
use Omnimap\MapRendererInterface;
use Omnimap\Model\MapView;
use Omnimap\Model\Size;
use Omnimap\Signer;
use Omnimap\StaticMapInterface;

/**
 * The map cache, whatever the provider: a view is named by the signature of
 * what its picture depends on (the renderer's fingerprint, signed), and its
 * picture is kept in a SnapshotStore. The picture comes from the browser (an
 * administrator's capture of the live map, save()) or from the server
 * (make(), when a StaticMapInterface is given: the provider's static map
 * service, or its tiles put together) - the cache does not care which.
 *
 *   $snapshots = new Snapshots($renderer, new Signer($secret), new SnapshotStore(new DirectoryStorage('/var/maps')), $staticMap);
 *   $snapshot = $snapshots->findOrMake($view);   // made once, then read
 */
final class Snapshots
{
    public function __construct(
        private readonly MapRendererInterface $renderer,
        private readonly Signer $signer,
        private readonly SnapshotStore $store,
        private readonly ?StaticMapInterface $staticMap = null,
        private readonly Size $size = new Size(1512, 938),
        private readonly int $scale = 1,
        private readonly ?int $tilesize = 512,
    ) {
    }

    public function signature(MapView $view): string
    {
        return $this->signer->sign($this->renderer->fingerprint($view));
    }

    public function find(MapView $view): ?Snapshot
    {
        return $this->store->find($this->signature($view));
    }

    /** Whether the server can make a picture itself (a static map is configured). */
    public function canMake(): bool
    {
        return null !== $this->staticMap;
    }

    /** The picture of the view, made now by the server and kept (replacing any). */
    public function make(MapView $view): Snapshot
    {
        if (null === $this->staticMap) {
            throw new NotSupportedException('No static map is configured: the pictures come from the browser (save()).');
        }
        $image = $this->staticMap->image($view, $this->size, $this->scale);

        return $this->store->save($this->signature($view), $image->bytes, $this->tilesize);
    }

    /** The picture kept; made now when there is none and the server can make one. */
    public function findOrMake(MapView $view): ?Snapshot
    {
        return $this->find($view) ?? ($this->canMake() ? $this->make($view) : null);
    }

    /** A picture the browser captured (an image's bytes, or a data: URL), kept under its signature. */
    public function save(string $signature, string $image, ?int $tilesize = null): Snapshot
    {
        if (str_starts_with($image, 'data:')) {
            $image = (string) base64_decode(substr($image, (int) strpos($image, ',') + 1), true);
        }

        return $this->store->save($signature, $image, $tilesize ?? $this->tilesize);
    }

    public function delete(string $signature): void
    {
        $this->store->delete($signature);
    }

    public function store(): SnapshotStore
    {
        return $this->store;
    }

    public function renderer(): MapRendererInterface
    {
        return $this->renderer;
    }
}
