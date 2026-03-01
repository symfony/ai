<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\InputProcessor;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessor\CacheRetentionInputProcessor;
use Symfony\AI\Platform\Message\MessageBag;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class CacheRetentionInputProcessorTest extends TestCase
{
    public function testDefaultsToShortRetention()
    {
        $processor = new CacheRetentionInputProcessor();
        $input = new Input('claude-3-5-sonnet-latest', new MessageBag());

        $processor->processInput($input);

        $this->assertSame('short', $input->getOptions()['cacheRetention']);
    }

    public function testSetsShortRetention()
    {
        $processor = new CacheRetentionInputProcessor('short');
        $input = new Input('claude-3-5-sonnet-latest', new MessageBag());

        $processor->processInput($input);

        $this->assertSame('short', $input->getOptions()['cacheRetention']);
    }

    public function testSetsLongRetention()
    {
        $processor = new CacheRetentionInputProcessor('long');
        $input = new Input('claude-3-5-sonnet-latest', new MessageBag());

        $processor->processInput($input);

        $this->assertSame('long', $input->getOptions()['cacheRetention']);
    }

    public function testSetsNoneRetention()
    {
        $processor = new CacheRetentionInputProcessor('none');
        $input = new Input('claude-3-5-sonnet-latest', new MessageBag());

        $processor->processInput($input);

        $this->assertSame('none', $input->getOptions()['cacheRetention']);
    }

    public function testOverridesExistingCacheRetentionOption()
    {
        $processor = new CacheRetentionInputProcessor('long');
        $input = new Input(
            'claude-3-5-sonnet-latest',
            new MessageBag(),
            options: ['cacheRetention' => 'none'],
        );

        $processor->processInput($input);

        $this->assertSame('long', $input->getOptions()['cacheRetention']);
    }

    public function testPreservesOtherOptions()
    {
        $processor = new CacheRetentionInputProcessor('short');
        $input = new Input(
            'claude-3-5-sonnet-latest',
            new MessageBag(),
            options: ['temperature' => 0.7, 'max_tokens' => 2048],
        );

        $processor->processInput($input);

        $options = $input->getOptions();
        $this->assertSame('short', $options['cacheRetention']);
        $this->assertSame(0.7, $options['temperature']);
        $this->assertSame(2048, $options['max_tokens']);
    }
}
