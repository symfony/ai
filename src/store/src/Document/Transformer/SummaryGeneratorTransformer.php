<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Document\Transformer;

use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\TransformerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Generates LLM summaries for documents and stores them in metadata.
 *
 * When $yieldSummaryDocuments is true, also yields an additional TextDocument
 * with the summary as content (dual-indexing mode).
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SummaryGeneratorTransformer implements TransformerInterface
{
    private const DEFAULT_SYSTEM_PROMPT = 'Summarize the following text in 2-3 sentences, capturing the key concepts and any technical terms. Be concise and precise.';

    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly string $model,
        private readonly bool $yieldSummaryDocuments = false,
        private readonly string $systemPrompt = self::DEFAULT_SYSTEM_PROMPT,
    ) {
    }

    public function transform(iterable $documents, array $options = []): iterable
    {
        foreach ($documents as $document) {
            $summary = $this->generateSummary($document->getContent());
            $document->getMetadata()->setSummary($summary);

            yield $document;

            if ($this->yieldSummaryDocuments) {
                $summaryMetadata = new Metadata([...$document->getMetadata()]);
                $summaryMetadata->setText($summary);

                yield new TextDocument(Uuid::v4()->toRfc4122(), $summary, $summaryMetadata);
            }
        }
    }

    private function generateSummary(string $content): string
    {
        $messages = new MessageBag(
            Message::forSystem($this->systemPrompt),
            Message::ofUser($content),
        );

        return $this->platform->invoke($this->model, $messages)->asText();
    }
}
