<?php

namespace Omnimap\Model;

use Omnimap\Exception\InvalidArgumentException;

/** A width and a height in pixels. */
final readonly class Size implements \JsonSerializable, \Stringable
{
    public function __construct(public int $width, public int $height)
    {
        if ($width < 1 || $height < 1) {
            throw new InvalidArgumentException(\sprintf('A size is at least one pixel each way, not %dx%d.', $width, $height));
        }
    }

    /** "640x480" */
    public static function fromString(string $size): self
    {
        if (!preg_match('~^(\d+)x(\d+)$~', $size, $match)) {
            throw new InvalidArgumentException(\sprintf('A size is written WIDTHxHEIGHT, not "%s".', $size));
        }

        return new self((int) $match[1], (int) $match[2]);
    }

    public function scaled(int $scale): self
    {
        return new self($this->width * $scale, $this->height * $scale);
    }

    /** @return array{width: int, height: int} */
    public function jsonSerialize(): array
    {
        return ['width' => $this->width, 'height' => $this->height];
    }

    public function __toString(): string
    {
        return $this->width.'x'.$this->height;
    }
}
