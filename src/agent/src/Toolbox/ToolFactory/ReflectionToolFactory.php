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
        private readonly Factory $factory = new Factory(),
    ) {
    }

    public function getTool(object|string $reference): iterable
    {
        $className = \is_object($reference) ? $reference::class : $reference;

        if (!class_exists($className)) {
            throw ToolException::invalidReference($className);
        }

        $reflectionClass = new \ReflectionClass($className);
        $attributes = $reflectionClass->getAttributes(AsTool::class);

        if ([] === $attributes) {
            throw ToolException::missingAttribute($className);
        }

        foreach ($attributes as $attribute) {
            $asTool = $attribute->newInstance();

            try {
                yield new Tool(
                    new ExecutionReference($className, $asTool->method),
                    $asTool->name,
                    $asTool->description,
                    $this->factory->buildParameters($className, $asTool->method),
                );
            } catch (\ReflectionException $e) {
                throw ToolConfigurationException::invalidMethod($className, $asTool->method, $e);
            }
        }
    }
}
