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

use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\Component\JsonPath\JsonCrawler;
use Symfony\Component\JsonPath\JsonPath;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
readonly class ToolCallResultExtractor implements ResultExtractorInterface
{
    public function __construct(
        private string|JsonPath $toolCalls = '$.choices[*].message.tool_calls',
        private string|JsonPath $idPath = '$.choices[*].message.tool_calls[*].id',
        private string|JsonPath $functionNamePath = '$.choices[*].message.tool_calls[*].function.name',
        private string|JsonPath $functionArgumentsPath = '$.choices[*].message.tool_calls[*].function.arguments',
    ) {
    }

    public function supports(JsonCrawler $crawler): bool
    {
        return [] !== $crawler->find($this->toolCalls);
    }

    public function extract(JsonCrawler $crawler): array
    {
        $choices = $crawler->find($this->toolCalls);
        $ids = $crawler->find($this->idPath);
        $functionNames = $crawler->find($this->functionNamePath);
        $functionArguments = $crawler->find($this->functionArgumentsPath);

        $results = [];
        foreach ($choices as $toolCallResult) {
            $limit = min(\count($functionNames), \count($toolCallResult));

            $toolCalls = [];
            for ($i = 0; $i < $limit; ++$i) {
                $args = array_shift($functionArguments);
                $toolCalls[] = new ToolCall(
                    array_shift($ids) ?? $i,
                    array_shift($functionNames),
                    \is_string($args) ? json_decode($args, true) : $args,
                );
            }

            $results[] = new ToolCallResult(...$toolCalls);
        }

        return $results;
    }
}
