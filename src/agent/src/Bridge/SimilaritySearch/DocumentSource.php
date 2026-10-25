<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\SimilaritySearch;

use Symfony\AI\Agent\Toolbox\Source\Source;
use Symfony\AI\Store\Document\VectorDocumentInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class DocumentSource extends Source
{
    public function __construct(
        private readonly VectorDocumentInterface $document,
    ) {
        $metadata = $document->getMetadata();
        $reference = $metadata->getSource() ?? (string) $document->getId();

        parent::__construct($metadata->getTitle() ?? $reference, $reference, $metadata->getText() ?? '');
    }

    public function getDocument(): VectorDocumentInterface
    {
        return $this->document;
    }
}
