<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\ResultExtractor;

use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\JsonPath\JsonCrawler;
use Symfony\Component\JsonPath\JsonPath;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
readonly class TextResultExtractor implements ResultExtractorInterface
{
    public function __construct(
        private string|JsonPath $jsonPath = '$.choices[?length(@.message.content) >= 0].message.content',
    ) {
    }

    public function supports(JsonCrawler $crawler): bool
    {
        return [] !== array_filter($crawler->find($this->jsonPath));
    }

    public function extract(JsonCrawler $crawler): array
    {
        $data = $crawler->find($this->jsonPath);

        return array_map(static fn (string $text): TextResult => new TextResult($text), $data);
    }
}
