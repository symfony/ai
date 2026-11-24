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

use PrinsFrank\PdfParser\Exception\PdfParserException;
use PrinsFrank\PdfParser\PdfParser;
use Symfony\AI\Store\Document\LoaderInterface;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\Component\Uid\Uuid;

/**
 * @author PrinsFrank
 */
final class PdfLoader implements LoaderInterface
{
    public function __construct(
        private readonly bool $asMarkdown = true,
    ) {
        if (!class_exists(PdfParser::class)) {
            throw new RuntimeException('For using the PdfLoader, the prinsfrank/pdfparser library is required. Try running "composer require prinsfrank/pdfparser".');
        }
    }

    public function load(?string $source = null, array $options = []): iterable
    {
        if (null === $source) {
            throw new InvalidArgumentException('PdfLoader requires a file path as source, null given.');
        }

        try {
            $document = (new PdfParser())
                ->parseFile($source);

            $informationDictionary = $document->getInformationDictionary();
            $metadata = new Metadata(array_filter([
                Metadata::KEY_SOURCE => $source,
                Metadata::KEY_TITLE => $informationDictionary?->getTitle(),
                'author' => $informationDictionary?->getAuthor(),
                'creator' => $informationDictionary?->getCreator(),
                'producer' => $informationDictionary?->getProducer(),
                'subject' => $informationDictionary?->getSubject(),
                'keywords' => $informationDictionary?->getKeywords(),
                'creationDate' => $informationDictionary?->getCreationDate(),
                'modificationDate' => $informationDictionary?->getModificationDate(),
            ]));

            $content = $this->asMarkdown
                ? $document->getMarkdown()
                : $document->getText();
        } catch (PdfParserException $e) {
            throw new InvalidArgumentException(\sprintf('Unable to parse "%s": "%s"', $source, $e->getMessage()), previous: $e);
        }

        yield new TextDocument(
            Uuid::v4()->toRfc4122(),
            $content,
            $metadata,
        );
    }
}
