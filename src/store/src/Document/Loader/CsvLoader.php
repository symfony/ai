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
 * Loads documents from CSV files.
 *
 * Each row becomes a TextDocument. One column provides the content,
 * optional columns can be mapped as metadata.
 *
 * @author Ramy Hakam <ramyhakam1@gmail.com>
 */
final class CsvLoader implements LoaderInterface
{
    public const OPTION_CONTENT_COLUMN = 'content_column';
    public const OPTION_ID_COLUMN = 'id_column';
    public const OPTION_METADATA_COLUMNS = 'metadata_columns';
    public const OPTION_DELIMITER = 'delimiter';
    public const OPTION_ENCLOSURE = 'enclosure';
    public const OPTION_ESCAPE = 'escape';
    public const OPTION_HAS_HEADER = 'has_header';

    /**
     * @param array<string|int> $metadataColumns
     */
    public function __construct(
        private readonly string|int $contentColumn = 'content',
        private readonly string|int|null $idColumn = null,
        private readonly array $metadataColumns = [],
        private readonly string $delimiter = ',',
        private readonly string $enclosure = '"',
        private readonly string $escape = '\\',
        private readonly bool $hasHeader = true,
    ) {
    }

    public function load(?string $source = null, array $options = []): iterable
    {
        if (null === $source) {
            throw new InvalidArgumentException('CSV loader requires a file path as source.');
        }

        if (!is_file($source)) {
            throw new RuntimeException(\sprintf('File "%s" does not exist.', $source));
        }

        $handle = fopen($source, 'r');
        if (false === $handle) {
            throw new RuntimeException(\sprintf('Unable to open file "%s".', $source));
        }

        $contentColumn = $options[self::OPTION_CONTENT_COLUMN] ?? $this->contentColumn;
        $idColumn = $options[self::OPTION_ID_COLUMN] ?? $this->idColumn;
        $metadataColumns = $options[self::OPTION_METADATA_COLUMNS] ?? $this->metadataColumns;
        $delimiter = $options[self::OPTION_DELIMITER] ?? $this->delimiter;
        $enclosure = $options[self::OPTION_ENCLOSURE] ?? $this->enclosure;
        $escape = $options[self::OPTION_ESCAPE] ?? $this->escape;
        $hasHeader = $options[self::OPTION_HAS_HEADER] ?? $this->hasHeader;

        try {
            $headers = null;
            $rowIndex = 0;

            while (false !== ($row = fgetcsv($handle, 0, $delimiter, $enclosure, $escape))) {
                if ([null] === $row) {
                    continue;
                }

                if ($hasHeader && null === $headers) {
                    $headers = $row;

                    if (\is_string($contentColumn) && !\in_array($contentColumn, $headers, true)) {
                        throw new InvalidArgumentException(\sprintf('Content column "%s" not found in CSV headers.', $contentColumn));
                    }

                    continue;
                }

                $data = $this->normalizeRow($row, $headers, $source, $rowIndex);

                $content = $data[$contentColumn] ?? null;
                if (null === $content || '' === trim($content)) {
                    ++$rowIndex;
                    continue;
                }

                $documentId = $this->resolveDocumentId($data, $idColumn);
                $metadata = $this->buildMetadata($data, $metadataColumns, $source, $rowIndex);

                yield new TextDocument(
                    $documentId,
                    trim($content),
                    new Metadata($metadata)
                );

                ++$rowIndex;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param array<int, string>      $row
     * @param array<int, string>|null $headers
     *
     * @return array<string|int, string>
     */
    private function normalizeRow(array $row, ?array $headers, string $source, int $rowIndex): array
    {
        if (null === $headers) {
            return $row;
        }

        if (\count($row) !== \count($headers)) {
            $row = array_pad($row, \count($headers), '');
        }

        return array_combine($headers, $row);
    }

    /**
     * @param array<string|int, string> $data
     */
    private function resolveDocumentId(array $data, string|int|null $idColumn): string
    {
        if (null === $idColumn) {
            return Uuid::v4();
        }

        $id = $data[$idColumn] ?? null;

        return null !== $id && '' !== $id ? $id : Uuid::v4();
    }

    /**
     * @param array<string|int, string> $data
     * @param array<string|int>         $metadataColumns
     *
     * @return array<string, mixed>
     */
    private function buildMetadata(array $data, array $metadataColumns, string $source, int $rowIndex): array
    {
        $metadata = [
            Metadata::KEY_SOURCE => $source,
            '_row_index' => $rowIndex,
        ];

        foreach ($metadataColumns as $column) {
            $value = $data[$column] ?? null;
            if (null !== $value) {
                $metadata[\is_int($column) ? 'column_'.$column : $column] = $value;
            }
        }

        return $metadata;
    }
}
