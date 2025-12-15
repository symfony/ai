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

use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\Source\TextFile;
use Symfony\AI\Store\Document\SourceInterface;
use Symfony\AI\Store\Document\SourceLoaderInterface;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\Component\Uid\Uuid;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TextFileLoader implements SourceLoaderInterface
{
    public static function createSource(string|array $source): iterable
    {
        foreach ((array) $source as $filePath) {
            if (!\is_string($filePath)) {
                throw new InvalidArgumentException(\sprintf('"%s" requires a string or an array of strings as source, "%s" given.', self::class, get_debug_type($url)));
            }

            if (!is_file($filePath)) {
                throw new InvalidArgumentException(\sprintf('File "%s" does not exist.', $filePath));
            }

            yield new TextFile($filePath);
        }
    }

    public static function supportedSource(): string
    {
        return TextFile::class;
    }

    public function load(SourceInterface|TextFile $source, array $options = []): iterable
    {
        $content = file_get_contents($source->getFilePath());

        if (false === $content) {
            throw new RuntimeException(\sprintf('Unable to read file "%s"', $source));
        }

        yield new TextDocument(Uuid::v4(), trim($content), new Metadata([
            Metadata::KEY_SOURCE => $source,
        ]));
    }
}
