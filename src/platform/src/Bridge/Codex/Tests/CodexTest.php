<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Codex\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Codex\Codex;
use Symfony\AI\Platform\Capability;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class CodexTest extends TestCase
{
    public function testItCreatesCodexWithDefaults()
    {
        $model = new Codex('gpt-5-codex');

        $this->assertSame('gpt-5-codex', $model->getName());
        $this->assertSame([], $model->getOptions());
    }

    public function testItCreatesCodexWithCapabilities()
    {
        $capabilities = [Capability::INPUT_TEXT, Capability::OUTPUT_TEXT];
        $model = new Codex('gpt-5.4', $capabilities);

        $this->assertSame('gpt-5.4', $model->getName());
        $this->assertSame($capabilities, $model->getCapabilities());
    }

    public function testItCreatesCodexWithOptions()
    {
        $options = ['sandbox' => 'workspace-write', 'model' => 'gpt-5-codex'];
        $model = new Codex('gpt-5-codex', [], $options);

        $this->assertSame('gpt-5-codex', $model->getName());
        $this->assertSame($options, $model->getOptions());
    }
}
