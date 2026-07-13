<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Context;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Context\Context;
use Symfony\AI\Agent\Context\Instruction;
use Symfony\AI\Platform\Message\Content\Text;

final class ContextTest extends TestCase
{
    public function testItIsEmptyByDefault()
    {
        $context = new Context();

        $this->assertCount(0, $context);
        $this->assertSame([], $context->all());
    }

    public function testWithReturnsANewContextAndLeavesTheOriginalUntouched()
    {
        $context = new Context();
        $instruction = new Instruction('Be brief.');

        $extended = $context->with($instruction);

        $this->assertCount(0, $context);
        $this->assertCount(1, $extended);
        $this->assertSame([$instruction], $extended->all());
    }

    public function testMergeCombinesTheItemsOfBothContexts()
    {
        $instruction = new Instruction('Be brief.');
        $text = new Text('Some attachment');

        $merged = (new Context($instruction))->merge(new Context($text));

        $this->assertSame([$instruction, $text], $merged->all());
    }

    public function testAllFiltersByType()
    {
        $instruction = new Instruction('Be brief.');
        $context = new Context($instruction, new Text('Some attachment'));

        $this->assertSame([$instruction], $context->all(Instruction::class));
    }

    public function testHasChecksForAType()
    {
        $context = new Context(new Instruction('Be brief.'));

        $this->assertTrue($context->has(Instruction::class));
        $this->assertFalse($context->has(Text::class));
    }

    public function testWithoutRemovesAllItemsOfAType()
    {
        $text = new Text('Some attachment');
        $context = new Context(new Instruction('Be brief.'), new Instruction('Be kind.'), $text);

        $stripped = $context->without(Instruction::class);

        $this->assertSame([$text], $stripped->all());
        $this->assertCount(3, $context);
    }

    public function testItIsIterable()
    {
        $instruction = new Instruction('Be brief.');
        $text = new Text('Some attachment');

        $this->assertSame([$instruction, $text], iterator_to_array(new Context($instruction, $text)));
    }
}
