<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\EventListener;

use Symfony\AI\Agent\Toolbox\Event\ToolCallArgumentsResolved;
use Symfony\AI\Agent\Toolbox\Exception\InvalidToolCallArgumentsException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ValidateToolCallArgumentsListener
{
    public function __construct(private readonly ValidatorInterface $validator)
    {
    }

    public function __invoke(ToolCallArgumentsResolved $event): void
    {
        $validator = $this->validator->startContext();
        foreach ($event->getArguments() as $name => $argument) {
            if (!\is_object($argument)) {
                continue;
            }

            $validator->atPath($name)->validate($argument);
        }

        if (\count($violations = $validator->getViolations())) {
            throw new InvalidToolCallArgumentsException(\sprintf('Invalid arguments provided for "%s" tool.', $event->getMetadata()->getName()), 0, null, $violations);
        }
    }
}
