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
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolCrayon;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolWithConstraints;
use Symfony\AI\Agent\Toolbox\Event\ToolCallArgumentsResolved;
use Symfony\AI\Agent\Toolbox\EventListener\ValidateToolCallArgumentsListener;
use Symfony\AI\Agent\Toolbox\Exception\InvalidToolCallArgumentsException;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
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

    public function testInvokeWithScalarArguments()
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $listener = new ValidateToolCallArgumentsListener($validator);

        $crayon = new ToolCrayon();

        $event = new ToolCallArgumentsResolved(
            $crayon,
            new Tool(new ExecutionReference($crayon::class), 'get_crayon', 'Get a crayon'),
            ['color' => 'blue'],
        );

        $listener($event);
        $this->assertCount(1, $event->getArguments());
        $this->assertSame('blue', $event->getArguments()['color']);
    }

    public function testWhenValidationHasFailed()
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $listener = new ValidateToolCallArgumentsListener($validator);

        $tool = new ToolWithConstraints();

        $event = new ToolCallArgumentsResolved(
            $tool,
            new Tool(new ExecutionReference($tool::class), 'get_recipe', 'Get one-ingredient recipe'),
            ['recipe' => new Recipe('salt')],
        );

        try {
            $listener($event);
            $this->fail('Should have thrown before!');
        } catch (InvalidToolCallArgumentsException $ex) {
            $this->assertSame('Invalid arguments provided for "get_recipe" tool.', $ex->getMessage());
            $toolCallResult = $ex->getToolCallResult();
            $this->assertInstanceOf(ConstraintViolationList::class, $toolCallResult);
            $this->assertSame(1, $toolCallResult->count());
            $violation = iterator_to_array($toolCallResult)[0];
            $this->assertInstanceOf(ConstraintViolation::class, $violation);
            $this->assertSame('The value must be one of "flour", "sugar", "butter".', $violation->getMessage());
        }
    }
}
