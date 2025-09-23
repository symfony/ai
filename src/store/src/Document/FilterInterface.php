<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Document;

/**
 * A Filter is designed to filter a stream of TextDocuments with the purpose of removing unwanted documents.
 * It should not act blocking, but is expected to iterate over incoming documents and yield only those that pass the filter criteria.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
interface FilterInterface
{
    /**
     * @param iterable<TextDocument> $documents
     * @param array<string, mixed>   $options
     *
     * @return iterable<TextDocument>
     */
    public function filter(iterable $documents, array $options = []): iterable;
}
