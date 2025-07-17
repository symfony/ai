<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\StructuredOutput\Responses;

use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use function Symfony\Component\String\u;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class ResponsesResponseFormatFactory implements ResponsesResponseFormatFactoryInterface
{
    public function __construct(
        private Factory $schemaFactory = new Factory(),
    ) {
    }

    public function create(string $responseClass): array
    {
        return [
            'format' => [
                'type' => 'json_schema',
                'name' => u($responseClass)->afterLast('\\')->toString(),
                'strict' => true,
                'schema' => $this->schemaFactory->buildProperties($responseClass),
            ],
        ];
    }
}
