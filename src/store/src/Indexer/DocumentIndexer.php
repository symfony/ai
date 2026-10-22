<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Indexer;

use Symfony\AI\Store\Document\DocumentInterface;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\IndexerInterface;

/**
 * Indexer that accepts documents directly and processes them.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class DocumentIndexer implements IndexerInterface
{
    public function __construct(
        private DocumentProcessor $processor,
    ) {
    }

    /**
     * @param DocumentInterface|iterable<DocumentInterface> $input
     */
    public function index(string|iterable|object $input, array $options = []): void
    {
        if (!$input instanceof DocumentInterface && !is_iterable($input)) {
            throw new InvalidArgumentException(\sprintf('DocumentIndexer expects an DocumentInterface or iterable, got "%s".', get_debug_type($input)));
        }

        $this->processor->process($input instanceof DocumentInterface ? [$input] : $input, $options);
    }
}
