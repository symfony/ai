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
 * @phpstan-import-type JsonSchema from Factory
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface SchemaFactoryInterface
{
    /**
     * @param array<string, mixed> $context Describer context, e.g. `['serializer_groups' => ['write']]`
     *
     * @return JsonSchema|null
     */
    public function buildParameters(string $className, string $methodName, array $context = []): ?array;

    /**
     * @param array<string, mixed> $context Describer context, e.g. `['serializer_groups' => ['write']]`
     *
     * @return JsonSchema|null
     */
    public function buildProperties(string $className, array $context = []): ?array;
}
