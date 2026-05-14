<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Reranking\RerankingEntry;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\RerankingResult;

/**
 * Reshapes the user-friendly `{query, texts}` payload into the HF
 * text-classification pair format (`{inputs: [{text, text_pair}, ...]}`).
 * Parses both the TEI response shape (`[{index, score}, ...]`) and the HF
 * serverless cross-encoder shape (`[[{label, score}, ...]]`).
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TextRankingClient extends AbstractTaskClient
{
    public const ENDPOINT = 'hf.'.Task::TEXT_RANKING;

    public function endpoint(): string
    {
        return self::ENDPOINT;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        unset($options['provider']);

        if (\is_array($payload) && isset($payload['query'], $payload['texts'])) {
            $inputs = [];
            foreach ($payload['texts'] as $text) {
                $inputs[] = ['text' => $payload['query'], 'text_pair' => $text];
            }

            $envelope = new RequestEnvelope(
                payload: ['inputs' => $inputs],
                path: '/{provider}/models/{name}',
            );

            return $this->transport->send($model, $envelope, $options);
        }

        return parent::request($model, $payload, $options);
    }

    public function convert(RawResultInterface $raw, array $options = []): RerankingResult
    {
        $content = $raw->getData();

        // TEI format: [{index: 0, score: 0.95}, ...]
        if (isset($content[0]['index'])) {
            return new RerankingResult(array_map(
                static fn (array $item): RerankingEntry => new RerankingEntry((int) $item['index'], (float) $item['score']),
                $content,
            ));
        }

        // HF serverless cross-encoder: [[{label, score}, ...]] or [{label, score}, ...]
        $items = isset($content[0][0]) ? $content[0] : $content;

        $entries = [];
        foreach ($items as $index => $item) {
            $entries[] = new RerankingEntry((int) $index, (float) $item['score']);
        }

        return new RerankingResult($entries);
    }
}
