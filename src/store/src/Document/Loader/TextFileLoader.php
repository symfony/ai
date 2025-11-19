<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Document\Loader;

use Symfony\AI\Store\Document\LoaderInterface;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\Component\Uid\Uuid;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TextFileLoader implements LoaderInterface
{
    public function load(?string $source, array $options = []): iterable
    {
        if (null === $source) {
            throw new InvalidArgumentException('TextFileLoader requires a file path as source, null given.');
        }

        if (!is_file($source)) {
            throw new RuntimeException(\sprintf('File "%s" does not exist.', $source));
        }

        $content = file_get_contents($source);

        if (false === $content) {
            throw new RuntimeException(\sprintf('Unable to read file "%s"', $source));
        }

        yield new TextDocument(Uuid::v4(), trim($content), new Metadata([
            Metadata::KEY_SOURCE => $source,
        ]));
    }
}
