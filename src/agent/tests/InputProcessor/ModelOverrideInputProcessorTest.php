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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessor\ModelOverrideInputProcessor;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\OpenAI\Embeddings;
use Symfony\AI\Platform\Bridge\OpenAI\GPT;
use Symfony\AI\Platform\Message\MessageBag;

#[CoversClass(ModelOverrideInputProcessor::class)]
#[UsesClass(GPT::class)]
#[UsesClass(Claude::class)]
#[UsesClass(Input::class)]
#[UsesClass(MessageBag::class)]
#[UsesClass(Embeddings::class)]
#[Small]
final class ModelOverrideInputProcessorTest extends TestCase
{
    #[Test]
    public function processInputWithValidModelOption(): void
    {
        $gpt = new GPT();
        $claude = new Claude();
        $input = new Input($gpt, new MessageBag(), ['model' => $claude]);

        $processor = new ModelOverrideInputProcessor();
        $processor->processInput($input);

        self::assertSame($claude, $input->model);
    }

    #[Test]
    public function processInputWithoutModelOption(): void
    {
        $gpt = new GPT();
        $input = new Input($gpt, new MessageBag(), []);

        $processor = new ModelOverrideInputProcessor();
        $processor->processInput($input);

        self::assertSame($gpt, $input->model);
    }

    #[Test]
    public function processInputWithInvalidModelOption(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Option "model" must be an instance of Symfony\AI\Platform\Model.');

        $gpt = new GPT();
        $model = new MessageBag();
        $input = new Input($gpt, new MessageBag(), ['model' => $model]);

        $processor = new ModelOverrideInputProcessor();
        $processor->processInput($input);
    }
}
