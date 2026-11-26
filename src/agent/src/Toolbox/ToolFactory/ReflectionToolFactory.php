<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\ToolFactory;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Exception\ToolConfigurationException;
use Symfony\AI\Agent\Toolbox\Exception\ToolException;
use Symfony\AI\Agent\Toolbox\MapToolArgumentsDescriber;
use Symfony\AI\Agent\Toolbox\ToolFactoryInterface;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

/**
 * Metadata factory that uses reflection in combination with `#[AsTool]` attribute to extract metadata from tools.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ReflectionToolFactory implements ToolFactoryInterface
{
    public function __construct(
        private readonly Factory $factory = new Factory(new MapToolArgumentsDescriber()),
    ) {
    }

    public function getTool(object|string $reference): iterable
    {
        $className = \is_object($reference) ? $reference::class : $reference;

        if (!class_exists($className)) {
            throw ToolException::invalidReference($className);
        }

        $reflectionClass = new \ReflectionClass($className);
        $tools = [];

        foreach ($reflectionClass->getAttributes(AsTool::class) as $attribute) {
            $asTool = $attribute->newInstance();
            $tools[] = [$asTool, $asTool->method];
        }

        foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes(AsTool::class) as $attribute) {
                $asTool = $attribute->newInstance();

                if ('__invoke' !== $asTool->method && $method->getName() !== $asTool->method) {
                    throw ToolConfigurationException::conflictingMethod($className, $method->getName(), $asTool->method);
                }

                $tools[] = [$asTool, $method->getName()];
            }
        }

        if ([] === $tools) {
            throw ToolException::missingAttribute($className);
        }

        foreach ($tools as [$asTool, $methodName]) {
            try {
                yield new Tool(
                    new ExecutionReference($className, $methodName),
                    $asTool->name,
                    $asTool->description,
                    $this->factory->buildParameters($className, $methodName),
                    $asTool->metadata,
                    $asTool->effectHints,
                    $asTool->deferred,
                );
            } catch (\ReflectionException $e) {
                throw ToolConfigurationException::invalidMethod($className, $methodName, $e);
            }
        }
    }
}
