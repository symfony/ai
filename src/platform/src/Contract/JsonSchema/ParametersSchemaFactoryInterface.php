<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\JsonSchema;

/**
 * Builds the schema of the arguments of a method, e.g. a tool the model calls.
 *
 * @phpstan-import-type JsonSchema from Factory
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ParametersSchemaFactoryInterface
{
    /**
     * @param array<string, mixed> $context e.g. `['serializer_groups' => ['write']]`
     *
     * @return JsonSchema|null
     */
    public function buildParameters(string $className, string $methodName, array $context = []): ?array;
}
