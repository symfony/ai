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
use Symfony\AI\Agent\Toolbox\Exception\ToolException;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MemoryToolFactory extends AbstractToolFactory
{
    /**
     * @var array<string, AsTool[]>
     */
    private array $tools = [];

    public function addTool(string|object $class, string $name, string $description, string $method = '__invoke'): self
    {
        $className = \is_object($class) ? $class::class : $class;
        $this->tools[$className][] = new AsTool($name, $description, $method);

        return $this;
    }

    /**
     * @param class-string $className
     */
    public function getTool(string $className): iterable
    {
        if (!isset($this->tools[$className])) {
            throw ToolException::invalidReference($className);
        }

        foreach ($this->tools[$className] as $tool) {
            yield $this->convertAttribute($className, $tool);
        }
    }
}
