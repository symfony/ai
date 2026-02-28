<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ClaudeCode\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\ClaudeCode\ClaudeCode;
use Symfony\AI\Platform\Capability;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ClaudeCodeTest extends TestCase
{
    public function testItCreatesClaudeCodeWithDefaults()
    {
        $model = new ClaudeCode('sonnet');

        $this->assertSame('sonnet', $model->getName());
        $this->assertSame([], $model->getOptions());
    }

    public function testItCreatesClaudeCodeWithCapabilities()
    {
        $capabilities = [Capability::INPUT_TEXT, Capability::OUTPUT_TEXT];
        $model = new ClaudeCode('opus', $capabilities);

        $this->assertSame('opus', $model->getName());
        $this->assertSame($capabilities, $model->getCapabilities());
    }

    public function testItCreatesClaudeCodeWithOptions()
    {
        $options = ['max_turns' => 5, 'permission_mode' => 'plan'];
        $model = new ClaudeCode('haiku', [], $options);

        $this->assertSame('haiku', $model->getName());
        $this->assertSame($options, $model->getOptions());
    }
}
