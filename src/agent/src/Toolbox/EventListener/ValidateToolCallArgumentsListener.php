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
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ValidateToolCallArgumentsListener
{
    private readonly ValidatorInterface $validator;

    public function __construct(?ValidatorInterface $validator = null)
    {
        $this->validator = $validator ?? Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    public function __invoke(ToolCallArgumentsResolved $event): void
    {
        $method = new \ReflectionMethod($event->getMetadata()->getReference()->getClass(), $event->getMetadata()->getReference()->getMethod());

        /** @var array<string, \ReflectionParameter> $parameters */
        $parameters = array_column($method->getParameters(), null, 'name');

        $validator = $this->validator->startContext();
        foreach ($event->getArguments() as $name => $argument) {
            if (\is_object($argument)) {
                $validator->atPath($name)->validate($argument);
                continue;
            }

            if (!isset($parameters[$name])) {
                continue;
            }

            $constraints = $this->schemaConstraints($parameters[$name]);
            if ([] !== $constraints) {
                $validator->atPath($name)->validate($argument, $constraints);
            }
        }

        if (\count($violations = $validator->getViolations())) {
            throw new InvalidToolCallArgumentsException(\sprintf('Invalid arguments provided for "%s" tool.', $event->getMetadata()->getName()), 0, null, $violations);
        }
    }

    /**
     * Translates the runtime-enforceable keywords of a #[Schema] attribute into Symfony Validator
     * constraints, so scalar and array tool parameters get validated the same way object parameters do.
     *
     * @return list<Constraint>
     */
    private function schemaConstraints(\ReflectionParameter $parameter): array
    {
        $attributes = $parameter->getAttributes(Schema::class);
        if ([] === $attributes) {
            return [];
        }

        $schema = $attributes[0]->newInstance();

        if (null !== $schema->ref || null !== $schema->provider) {
            // Externally defined or runtime-computed schemas are not validated locally.
            return [];
        }

        $constraints = [];

        if (null !== $schema->pattern) {
            // #[Schema]'s pattern follows the JSON Schema convention (no PCRE delimiters), so it needs wrapping before preg_match() can use it.
            $constraints[] = new Assert\Regex('#'.str_replace('#', '\#', $schema->pattern).'#');
        }

        if (null !== $schema->minLength || null !== $schema->maxLength) {
            $constraints[] = new Assert\Length(min: $schema->minLength, max: $schema->maxLength);
        }

        if (null !== $schema->minimum) {
            $constraints[] = new Assert\GreaterThanOrEqual($schema->minimum);
        }

        if (null !== $schema->maximum) {
            $constraints[] = new Assert\LessThanOrEqual($schema->maximum);
        }

        if (null !== $schema->exclusiveMinimum) {
            $constraints[] = new Assert\GreaterThan($schema->exclusiveMinimum);
        }

        if (null !== $schema->exclusiveMaximum) {
            $constraints[] = new Assert\LessThan($schema->exclusiveMaximum);
        }

        if (null !== $schema->multipleOf) {
            $constraints[] = new Assert\DivisibleBy($schema->multipleOf);
        }

        if (null !== $schema->minItems || null !== $schema->maxItems) {
            $constraints[] = new Assert\Count(min: $schema->minItems, max: $schema->maxItems);
        }

        if (true === $schema->uniqueItems) {
            $constraints[] = new Assert\Unique();
        }

        if (null !== $schema->enum) {
            $constraints[] = new Assert\Choice(choices: $schema->enum);
        }

        if (null !== $schema->const) {
            $constraints[] = new Assert\EqualTo($schema->const);
        }

        return $constraints;
    }
}
