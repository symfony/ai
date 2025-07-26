<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\LMStudio\Completions;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\LMStudio\Completions;
use Symfony\AI\Platform\Bridge\LMStudio\Completions\ResultConverter;
use Symfony\AI\Platform\Bridge\OpenAI\GPT\ResultConverter as OpenAIResultConverter;

#[CoversClass(ResultConverter::class)]
#[UsesClass(Completions::class)]
#[UsesClass(OpenAIResultConverter::class)]
#[Small]
class ResultConverterTest extends TestCase
{
    #[Test]
    public function itSupportsCompletionsModel(): void
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new Completions('test-model')));
    }
}
