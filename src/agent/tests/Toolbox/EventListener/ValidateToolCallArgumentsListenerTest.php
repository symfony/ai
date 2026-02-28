<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox\EventListener;

use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Tests\Fixtures\Tool\Recipe;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolWithConstraints;
use Symfony\AI\Agent\Toolbox\Event\ToolCallArgumentsResolved;
use Symfony\AI\Agent\Toolbox\EventListener\ValidateToolCallArgumentsListener;
use Symfony\AI\Agent\Toolbox\Exception\InvalidToolCallArgumentsException;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\Validator\Validation;

class ValidateToolCallArgumentsListenerTest extends TestCase
{
    #[DoesNotPerformAssertions]
    public function testPassesValidation()
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $listener = new ValidateToolCallArgumentsListener($validator);

        $tool = new ToolWithConstraints();

        $event = new ToolCallArgumentsResolved(
            $tool,
            new Tool(new ExecutionReference($tool::class), 'get_recipe', 'Get one-ingredient recipe'),
            ['recipe' => new Recipe('sugar')],
        );

        $listener($event);
    }

    public function testFailsValidation()
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $listener = new ValidateToolCallArgumentsListener($validator);

        $tool = new ToolWithConstraints();

        $event = new ToolCallArgumentsResolved(
            $tool,
            new Tool(new ExecutionReference($tool::class), 'get_recipe', 'Get one-ingredient recipe'),
            ['recipe' => new Recipe('salt')],
        );

        $this->expectException(InvalidToolCallArgumentsException::class);
        $this->expectExceptionMessage('Invalid arguments provided for "get_recipe" tool.');
        $listener($event);
    }
}
