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

use Symfony\AI\Platform\Contract\JsonSchema\Describer\Describer;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\ModelDescriberInterface;

/**
 * @phpstan-type JsonSchema array{
 *     type: 'object',
 *     properties: array<string, array{
 *         type: string,
 *         description: string,
 *         enum?: list<string>,
 *         const?: string|int|list<string>,
 *         pattern?: string,
 *         minLength?: int,
 *         maxLength?: int,
 *         minimum?: int,
 *         maximum?: int,
 *         multipleOf?: int,
 *         exclusiveMinimum?: int,
 *         exclusiveMaximum?: int,
 *         minItems?: int,
 *         maxItems?: int,
 *         uniqueItems?: bool,
 *         minContains?: int,
 *         maxContains?: int,
 *         required?: bool,
 *         minProperties?: int,
 *         maxProperties?: int,
 *         dependentRequired?: bool,
 *         anyOf?: list<mixed>,
 *     }>,
 *     required: list<string>,
 *     additionalProperties: false,
 * }
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class Factory
{
    public function __construct(
        private readonly ModelDescriberInterface $modelDescriber = new Describer(),
    ) {
    }

    /**
     * @return JsonSchema|null
     */
    public function buildParameters(string $className, string $methodName): ?array
    {
        $schema = null;
        $this->modelDescriber->describeModel(new Model($className.'::'.$methodName, new \ReflectionMethod($className, $methodName)), $schema);

        return $schema;
    }

    /**
     * @return JsonSchema|null
     */
    public function buildProperties(string $className): ?array
    {
        $schema = null;
        $this->modelDescriber->describeModel(new Model($className, new \ReflectionClass($className)), $schema);

        return $schema;
    }
}
