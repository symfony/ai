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
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\String\UnicodeString;
use Symfony\Component\Uid\Uuid;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class MarkdownLoader implements LoaderInterface
{
    /**
     * @param array{strip_formatting?: bool} $options removes Markdown syntax from content
     */
    public function load(?string $source = null, array $options = []): iterable
    {
        if (!class_exists(UnicodeString::class)) {
            throw new RuntimeException('For using the MarkdownLoader, the Symfony String component is required. Try running "composer require symfony/string".');
        }

        if (!class_exists(Filesystem::class)) {
            throw new RuntimeException('For using the MarkdownLoader, the Symfony Filesystem component is required. Try running "composer require symfony/filesystem".');
        }

        if (null === $source) {
            throw new InvalidArgumentException('MarkdownLoader requires a file path as source, null given.');
        }

        $fs = new Filesystem();

        if (!$fs->exists($source)) {
            throw new RuntimeException(\sprintf('File "%s" does not exist.', $source));
        }

        $content = $fs->readFile($source);

        $text = new UnicodeString($content);
        $text = $text->trim();

        if ($text->isEmpty()) {
            return;
        }

        $metadata = [
            Metadata::KEY_SOURCE => $source,
        ];

        $match = $text->match('/^#\s+(.+)$/m');
        if (isset($match[1])) {
            $metadata['title'] = (new UnicodeString($match[1]))->trim()->toString();
        }

        if ($options['strip_formatting'] ?? false) {
            $text = self::stripMarkdown($text);
        }

        $text = $text->trim();

        if ($text->isEmpty()) {
            return;
        }

        yield new TextDocument(Uuid::v4()->toRfc4122(), $text->toString(), new Metadata($metadata));
    }

    /**
     * Strips common Markdown syntax to produce plain text.
     */
    private static function stripMarkdown(UnicodeString $text): UnicodeString
    {
        return $text
            ->replaceMatches('/```[\s\S]*?```/', '')
            ->replaceMatches('/`([^`]+)`/', '$1')
            ->replaceMatches('/!\[([^\]]*)\]\([^)]+\)/', '$1')
            ->replaceMatches('/\[([^\]]+)\]\([^)]+\)/', '$1')
            ->replaceMatches('/^#{1,6}\s+/m', '')
            ->replaceMatches('/(\*{1,3}|_{1,3})(.+?)\1/', '$2')
            ->replaceMatches('/~~(.+?)~~/', '$1')
            ->replaceMatches('/^>\s?/m', '')
            ->replaceMatches('/^[-*_]{3,}$/m', '')
            ->replaceMatches('/^[\s]*[-*+]\s+/m', '')
            ->replaceMatches('/^[\s]*\d+\.\s+/m', '')
            ->replaceMatches('/\n{3,}/', "\n\n")
        ;
    }
}
