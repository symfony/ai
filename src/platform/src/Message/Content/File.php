<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Message\Content;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;

use function Symfony\Component\String\u;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class File implements ContentInterface
{
    final public function __construct(
        private readonly string|\Closure $data,
        private readonly string $format,
        private readonly ?string $path = null,
    ) {
    }

    public static function fromDataUrl(string $dataUrl): static
    {
        if (!str_starts_with($dataUrl, 'data:')) {
            throw new InvalidArgumentException('Invalid audio data URL format.');
        }

        return new static(
            base64_decode(u($dataUrl)->after('base64,')->toString()),
            u($dataUrl)->after('data:')->before(';base64,')->toString(),
        );
    }

    public static function fromFile(string $path): static
    {
        if (!is_readable($path)) {
            throw new InvalidArgumentException(\sprintf('The file "%s" does not exist or is not readable.', $path));
        }

        return new static(
            static fn (): string => file_get_contents($path),
            mime_content_type($path),
            $path,
        );
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function asBinary(): string
    {
        return $this->data instanceof \Closure ? ($this->data)() : $this->data;
    }

    public function asBase64(): string
    {
        return base64_encode($this->asBinary());
    }

    public function asDataUrl(): string
    {
        return \sprintf('data:%s;base64,%s', $this->format, $this->asBase64());
    }

    public function asPath(): ?string
    {
        return $this->path;
    }

    /**
     * @return resource|false
     */
    public function asResource()
    {
        if (null === $this->path) {
            throw new RuntimeException('You can only get a resource after creating fromFile.');
        }

        return fopen($this->path, 'r');
    }

    public function getFilename(): ?string
    {
        return null === $this->path ? null : basename($this->path);
    }
}
