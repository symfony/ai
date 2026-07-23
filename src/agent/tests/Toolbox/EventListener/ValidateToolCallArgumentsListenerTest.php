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
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolWithScalarConstraints;
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
}
