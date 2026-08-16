<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Contract\JsonSchema;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolWithRenamedParameter;
use Symfony\AI\Platform\Contract\JsonSchema\SchemaNameResolver;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\CircuitMetadata;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\MathReasoningWithAttributes;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\TrainingSessionWithAccessors;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\WorkoutPlan;

final class SchemaNameResolverTest extends TestCase
{
    public function testForReflectorReturnsRenamedParameter()
    {
        $reflector = new \ReflectionParameter([CircuitMetadata::class, '__construct'], 'restBetweenRounds');

        $this->assertSame('rest_between_rounds', SchemaNameResolver::forReflector($reflector, 'restBetweenRounds'));
    }

    public function testForReflectorFallsBackToDefault()
    {
        $reflector = new \ReflectionParameter([CircuitMetadata::class, '__construct'], 'rounds');

        $this->assertSame('rounds', SchemaNameResolver::forReflector($reflector, 'rounds'));
    }

    public function testForReflectorResolvesToolParameters()
    {
        $reflector = new \ReflectionParameter([ToolWithRenamedParameter::class, '__invoke'], 'searchTerm');

        $this->assertSame('search_term', SchemaNameResolver::forReflector($reflector, 'searchTerm'));
    }

    public function testForClassOnlyContainsRenamedProperties()
    {
        $this->assertSame(['planTitle' => 'plan_title'], SchemaNameResolver::forClass(WorkoutPlan::class));
    }

    public function testForClassResolvesRenameDeclaredOnSetter()
    {
        $this->assertSame(['restBetweenRounds' => 'rest_between_rounds'], SchemaNameResolver::forClass(TrainingSessionWithAccessors::class));
    }

    public function testForClassIgnoresSerializerMetadata()
    {
        $this->assertSame([], SchemaNameResolver::forClass(MathReasoningWithAttributes::class));
    }

    public function testDescribesProperty()
    {
        $this->assertTrue(SchemaNameResolver::describesProperty('__construct'));
        $this->assertTrue(SchemaNameResolver::describesProperty('setRestBetweenRounds'));
        $this->assertFalse(SchemaNameResolver::describesProperty('set'));
        $this->assertFalse(SchemaNameResolver::describesProperty('__invoke'));
    }
}
