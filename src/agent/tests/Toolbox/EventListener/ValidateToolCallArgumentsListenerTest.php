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
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolConst;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolCrayon;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolEnum;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolExclusiveMaximum;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolExclusiveMinimum;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolMaxLength;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolMinLength;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolMultipleOf;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolWithConstraints;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolWithExternalRef;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolWithScalarConstraints;
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

    #[DoesNotPerformAssertions]
    public function testPassesValidationForScalarSchemaConstraints()
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $listener = new ValidateToolCallArgumentsListener($validator);

        $tool = new ToolWithScalarConstraints();

        $event = new ToolCallArgumentsResolved(
            $tool,
            new Tool(new ExecutionReference($tool::class), 'tool_with_scalar_constraints', 'A tool with #[Schema] constraints on scalar parameters'),
            ['reference' => 'ORD-2026-0042', 'quantity' => 5, 'ratings' => [1, 2, 3]],
        );

        $listener($event);
    }

    public function testFailsValidationForInvalidPattern()
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $listener = new ValidateToolCallArgumentsListener($validator);

        $tool = new ToolWithScalarConstraints();

        $event = new ToolCallArgumentsResolved(
            $tool,
            new Tool(new ExecutionReference($tool::class), 'tool_with_scalar_constraints', 'A tool with #[Schema] constraints on scalar parameters'),
            ['reference' => 'not-a-valid-reference', 'quantity' => 5, 'ratings' => [1, 2, 3]],
        );

        $this->expectException(InvalidToolCallArgumentsException::class);
        $this->expectExceptionMessage('Invalid arguments provided for "tool_with_scalar_constraints" tool.');
        $listener($event);
    }

    public function testFailsValidationForOutOfRangeNumber()
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $listener = new ValidateToolCallArgumentsListener($validator);

        $tool = new ToolWithScalarConstraints();

        $event = new ToolCallArgumentsResolved(
            $tool,
            new Tool(new ExecutionReference($tool::class), 'tool_with_scalar_constraints', 'A tool with #[Schema] constraints on scalar parameters'),
            ['reference' => 'ORD-2026-0042', 'quantity' => 42, 'ratings' => [1, 2, 3]],
        );

        $this->expectException(InvalidToolCallArgumentsException::class);
        $listener($event);
    }

    public function testFailsValidationForTooManyItems()
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $listener = new ValidateToolCallArgumentsListener($validator);

        $tool = new ToolWithScalarConstraints();

        $event = new ToolCallArgumentsResolved(
            $tool,
            new Tool(new ExecutionReference($tool::class), 'tool_with_scalar_constraints', 'A tool with #[Schema] constraints on scalar parameters'),
            ['reference' => 'ORD-2026-0042', 'quantity' => 5, 'ratings' => [1, 2, 3, 4]],
        );

        $this->expectException(InvalidToolCallArgumentsException::class);
        $listener($event);
    }

    public function testFailsValidationForDuplicateItems()
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $listener = new ValidateToolCallArgumentsListener($validator);

        $tool = new ToolWithScalarConstraints();

        $event = new ToolCallArgumentsResolved(
            $tool,
            new Tool(new ExecutionReference($tool::class), 'tool_with_scalar_constraints', 'A tool with #[Schema] constraints on scalar parameters'),
            ['reference' => 'ORD-2026-0042', 'quantity' => 5, 'ratings' => [1, 1, 2]],
        );

        $this->expectException(InvalidToolCallArgumentsException::class);
        $listener($event);
    }

    public function testExclusiveMinimumConstraint()
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $listener = new ValidateToolCallArgumentsListener($validator);

        $tool = new ToolExclusiveMinimum();

        $event = new ToolCallArgumentsResolved(
            $tool,
            new Tool(new ExecutionReference($tool::class), 'invoke', 'Tool with exclusive minimum'),
            ['value' => 5],
        );

        $this->expectException(InvalidToolCallArgumentsException::class);
        $listener($event);
    }

    public function testExclusiveMaximumConstraint()
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $listener = new ValidateToolCallArgumentsListener($validator);

        $tool = new ToolExclusiveMaximum();

        $event = new ToolCallArgumentsResolved(
            $tool,
            new Tool(new ExecutionReference($tool::class), 'invoke', 'Tool with exclusive maximum'),
            ['value' => 10],
        );

        $this->expectException(InvalidToolCallArgumentsException::class);
        $listener($event);
    }

    public function testMinLengthConstraint()
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $listener = new ValidateToolCallArgumentsListener($validator);

        $tool = new ToolMinLength();

        $event = new ToolCallArgumentsResolved(
            $tool,
            new Tool(new ExecutionReference($tool::class), 'invoke', 'Tool with min length'),
            ['text' => 'hi'],
        );

        $this->expectException(InvalidToolCallArgumentsException::class);
        $listener($event);
    }

    public function testMaxLengthConstraint()
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $listener = new ValidateToolCallArgumentsListener($validator);

        $tool = new ToolMaxLength();

        $event = new ToolCallArgumentsResolved(
            $tool,
            new Tool(new ExecutionReference($tool::class), 'invoke', 'Tool with max length'),
            ['text' => 'this is too long'],
        );

        $this->expectException(InvalidToolCallArgumentsException::class);
        $listener($event);
    }

    public function testMultipleOfConstraint()
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $listener = new ValidateToolCallArgumentsListener($validator);

        $tool = new ToolMultipleOf();

        $event = new ToolCallArgumentsResolved(
            $tool,
            new Tool(new ExecutionReference($tool::class), 'invoke', 'Tool with multiple of'),
            ['value' => 7],
        );

        $this->expectException(InvalidToolCallArgumentsException::class);
        $listener($event);
    }

    public function testEnumConstraint()
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $listener = new ValidateToolCallArgumentsListener($validator);

        $tool = new ToolEnum();

        $event = new ToolCallArgumentsResolved(
            $tool,
            new Tool(new ExecutionReference($tool::class), 'invoke', 'Tool with enum'),
            ['color' => 'purple'],
        );

        $this->expectException(InvalidToolCallArgumentsException::class);
        $listener($event);
    }

    public function testConstConstraint()
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $listener = new ValidateToolCallArgumentsListener($validator);

        $tool = new ToolConst();

        $event = new ToolCallArgumentsResolved(
            $tool,
            new Tool(new ExecutionReference($tool::class), 'invoke', 'Tool with const'),
            ['version' => '2.0'],
        );

        $this->expectException(InvalidToolCallArgumentsException::class);
        $listener($event);
    }

    public function testArgumentNotInParameters()
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $listener = new ValidateToolCallArgumentsListener($validator);

        $tool = new ToolCrayon();

        $event = new ToolCallArgumentsResolved(
            $tool,
            new Tool(new ExecutionReference($tool::class), 'get_crayon', 'Get a crayon'),
            ['color' => 'blue', 'extraParam' => 'should be ignored'],
        );

        $listener($event);
        $this->assertCount(2, $event->getArguments());
    }

    #[DoesNotPerformAssertions]
    public function testSchemaWithExternalRef()
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $listener = new ValidateToolCallArgumentsListener($validator);

        $tool = new ToolWithExternalRef();

        $event = new ToolCallArgumentsResolved(
            $tool,
            new Tool(new ExecutionReference($tool::class), 'invoke', 'Tool with external ref'),
            ['data' => 'any value'],
        );

        $listener($event);
    }
}
