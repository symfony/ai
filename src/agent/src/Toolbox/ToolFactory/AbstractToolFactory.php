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
use Symfony\AI\Agent\Toolbox\ToolFactoryInterface;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
abstract class AbstractToolFactory implements ToolFactoryInterface
{
    public function __construct(
        private readonly Factory $factory = new Factory(),
    ) {
    }

    protected function convertAttribute(string $className, AsTool $attribute): Tool
    {
        try {
            return new Tool(
                new ExecutionReference($className, $attribute->method),
                $attribute->name,
                $attribute->description,
                $this->factory->buildParameters($className, $attribute->method)
            );
        } catch (\ReflectionException $e) {
            throw ToolConfigurationException::invalidMethod($className, $attribute->method, $e);
        }
    }
}
