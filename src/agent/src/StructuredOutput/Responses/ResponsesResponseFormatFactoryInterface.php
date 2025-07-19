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

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
interface ResponsesResponseFormatFactoryInterface
{
    /**
     * @param class-string $responseClass
     *
     * @return array{
     *     format: array{
     *         type: 'json_schema|text|json_object',
     *         name?: string,
     *         schema?: array<string, mixed>,
     *         strict?: true,
     *     }
     * }
     */
    public function create(string $responseClass): array;
}
