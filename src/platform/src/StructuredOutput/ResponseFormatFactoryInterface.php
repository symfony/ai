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
     * @param class-string $responseClass
     * @param object|null  $instanceToPopulate An instance of $responseClass to describe only the properties it is
     *                                         still missing, or null to describe the whole class
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
    public function create(string $responseClass, ?object $instanceToPopulate = null): array;
}
