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

use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\Component\JsonPath\JsonCrawler;
use Symfony\Component\JsonPath\JsonPath;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
readonly class VectorResultExtractor implements ResultExtractorInterface
{
    public function __construct(
        private string|JsonPath $jsonPath = '$.data[*].embedding',
    ) {
    }

    public function supports(JsonCrawler $crawler): bool
    {
        return [] !== $crawler->find($this->jsonPath);
    }

    public function extract(JsonCrawler $crawler): array
    {
        $data = $crawler->find($this->jsonPath);

        return [new VectorResult(
            ...array_map(static fn (array $vector): Vector => new Vector($vector), $data),
        )];
    }
}
