f<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Document\Loader;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Smalot\PdfParser\Parser;
use Symfony\AI\Store\Document\LoaderInterface;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Uid\Uuid;

/**
 * @author Christopher X. Candreva <chris@westnet.com>
 */
final class PdfLoader implements LoaderInterface
{
    private readonly Parser $parser;

    public function __construct(
        ?Parser $parser = null,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        if (!class_exists(Parser::class)) {
            throw new RuntimeException('For using the PDF loader, the Smalot PDF parser is required. Try running "composer require smalot/pdfparser".');
        }

        $this->parser = $parser ?? new Parser();
    }

    public function load(?string $source = null, array $options = []): iterable
    {
        if (null === $source || !Path::isLocal($source)) {
            throw new InvalidArgumentException('PdfLoader requires a local file path as source.');
        }

        if (!is_file($source) || !is_readable($source)) {
            throw new RuntimeException(\sprintf('PDF file "%s" does not exist or is not a readable file.', $source));
        }

        $path = Path::makeAbsolute($source, getcwd());
        $hasText = false;

        try {
            $pdf = $this->parser->parseFile($source);
            $pages = $pdf->getPages();
            $details = $pdf->getDetails();
            $metadata = [
                Metadata::KEY_SOURCE => $source,
                Metadata::KEY_TITLE => $this->property($details, ['Title', 'dc:title']) ?? basename($path),
                'page_count' => \count($pages),
            ];

            foreach ([
                'pdf_author' => ['Author', 'dc:creator'],
                'pdf_subject' => ['Subject', 'dc:description'],
                'pdf_keywords' => ['Keywords', 'pdf:keywords', 'dc:subject'],
                'pdf_creation_date' => ['CreationDate', 'xmp:createdate'],
                'pdf_modification_date' => ['ModDate', 'xmp:modifydate'],
            ] as $key => $properties) {
                if (null !== $value = $this->property($details, $properties)) {
                    $metadata[$key] = $value;
                }
            }
        } catch (\Throwable $exception) {
            throw new RuntimeException(\sprintf('Unable to parse PDF file "%s": %s', $source, $exception->getMessage()), previous: $exception);
        }

        $pageNumber = 0;
        foreach ($pages as $page) {
            ++$pageNumber;
            try {
                $text = trim($page->getText());
            } catch (\Throwable $exception) {
                throw new RuntimeException(\sprintf('Unable to extract text from PDF file "%s": %s', $source, $exception->getMessage()), previous: $exception);
            }

            if ('' === $text) {
                continue;
            }

            $hasText = true;
            yield new TextDocument(
                Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), $path.'#page='.$pageNumber)->toRfc4122(),
                $text,
                new Metadata([...$metadata, 'page_number' => $pageNumber]),
            );
        }

        if (!$hasText) {
            $this->logger->warning(
                'PDF file "{source}" contains no extractable text; skipping.',
                ['source' => $source],
            );
        }
    }

    /**
     * @param array<string, mixed> $details
     * @param list<string>         $properties
     */
    private function property(array $details, array $properties): ?string
    {
        foreach ($properties as $property) {
            $value = $details[$property] ?? null;
            if (\is_string($value) && '' !== trim($value)) {
                return trim($value);
            }

            if (!\is_array($value) || !array_is_list($value)) {
                continue;
            }

            $values = [];
            foreach ($value as $item) {
                if (\is_string($item) && '' !== trim($item)) {
                    $values[] = trim($item);
                }
            }

            if ([] !== $values) {
                return implode('; ', $values);
            }
        }

        return null;
    }
}
