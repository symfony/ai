<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Workflow\Attribute;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Workflow\Attribute\AsWorkflowGuard;

final class AsWorkflowGuardTest extends TestCase
{
    public function testDefaults()
    {
        $attribute = new AsWorkflowGuard();

        $this->assertNull($attribute->workflow);
        $this->assertSame(0, $attribute->priority);
    }

    public function testWithValues()
    {
        $attribute = new AsWorkflowGuard('content_pipeline', 10);

        $this->assertSame('content_pipeline', $attribute->workflow);
        $this->assertSame(10, $attribute->priority);
    }

    public function testIsRepeatableClassAttribute()
    {
        $attributes = (new \ReflectionClass(AsWorkflowGuard::class))->getAttributes(\Attribute::class);

        $this->assertCount(1, $attributes);
        $this->assertSame(
            \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE,
            $attributes[0]->newInstance()->flags,
        );
    }
}
