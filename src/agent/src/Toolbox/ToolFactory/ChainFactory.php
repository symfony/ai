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

use Symfony\AI\Agent\Toolbox\Exception\ToolException;
use Symfony\AI\Agent\Toolbox\ToolFactoryInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class ChainFactory implements ToolFactoryInterface
{
    /**
     * @var list<ToolFactoryInterface>
     */
    private array $factories;

    /**
     * @param iterable<ToolFactoryInterface> $factories
     */
    public function __construct(iterable $factories)
    {
        $this->factories = $factories instanceof \Traversable ? iterator_to_array($factories) : $factories;
    }

    public function getTool(string $reference): iterable
    {
        $invalid = 0;
        foreach ($this->factories as $factory) {
            try {
                yield from $factory->getTool($reference);
            } catch (ToolException) {
                ++$invalid;
                continue;
            }

            // If the factory does not throw an exception, we don't need to check the others
            return;
        }

        if ($invalid === \count($this->factories)) {
            throw ToolException::invalidReference($reference);
        }
    }
}
