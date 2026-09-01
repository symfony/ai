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
use Symfony\AI\Platform\Contract\JsonSchema\SchemaNameConverter;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\MathReasoningWithAttributes;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\WorkoutPlan;

final class SchemaNameConverterTest extends TestCase
{
    private SchemaNameConverter $nameConverter;

    protected function setUp(): void
    {
        $this->nameConverter = new SchemaNameConverter();
    }

    public function testNormalizeRenamedProperty()
    {
        $this->assertSame('plan_title', $this->nameConverter->normalize('planTitle', WorkoutPlan::class));
    }

    public function testNormalizeUntouchedProperty()
    {
        $this->assertSame('circuits', $this->nameConverter->normalize('circuits', WorkoutPlan::class));
    }

    public function testNormalizeWithoutClass()
    {
        $this->assertSame('planTitle', $this->nameConverter->normalize('planTitle'));
    }

    public function testDenormalizeRenamedProperty()
    {
        $this->assertSame('planTitle', $this->nameConverter->denormalize('plan_title', WorkoutPlan::class));
    }

    public function testDenormalizeUntouchedProperty()
    {
        $this->assertSame('circuits', $this->nameConverter->denormalize('circuits', WorkoutPlan::class));
    }

    public function testDenormalizeWithoutClass()
    {
        $this->assertSame('plan_title', $this->nameConverter->denormalize('plan_title'));
    }

    public function testSerializedNameIsIgnored()
    {
        $this->assertSame('finalAnswer', $this->nameConverter->normalize('finalAnswer', MathReasoningWithAttributes::class));
        $this->assertSame('foo', $this->nameConverter->denormalize('foo', MathReasoningWithAttributes::class));
    }
}
