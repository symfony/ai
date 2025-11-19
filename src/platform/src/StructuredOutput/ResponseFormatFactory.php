<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\StructuredOutput;

use Symfony\AI\Platform\Contract\JsonSchema\Factory;

use function Symfony\Component\String\u;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ResponseFormatFactory implements ResponseFormatFactoryInterface
{
    public function __construct(
        private readonly Factory $schemaFactory = new Factory(),
    ) {
    }

    public function create(string $responseClass): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => u($responseClass)->afterLast('\\')->toString(),
                'schema' => $this->schemaFactory->buildProperties($responseClass),
                'strict' => true,
            ],
        ];
    }
}
