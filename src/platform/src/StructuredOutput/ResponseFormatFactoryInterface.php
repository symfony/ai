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

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
interface ResponseFormatFactoryInterface
{
    /**
     * @param class-string         $responseClass
     * @param array<string, mixed> $context       Describer context forwarded to `Contract\JsonSchema\Factory::buildProperties()`,
     *                                            e.g. `[Factory::CONTEXT_SELECTOR => $selector]` to describe only the properties
     *                                            the selector opens, or `['serializer_groups' => ['write']]`
     *
     * @return array{
     *     type: 'json_schema',
     *     json_schema: array{
     *         name: string,
     *         schema: array<string, mixed>,
     *         strict: true,
     *     }
     * }
     */
    public function create(string $responseClass, array $context = []): array;
}
