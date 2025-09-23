<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\AiMlApi\Completions;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\AiMlApi\Completions;
use Symfony\AI\Platform\Bridge\AiMlApi\Completions\ResultConverter;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt\ResultConverter as OpenAiResultConverter;

#[CoversClass(ResultConverter::class)]
#[UsesClass(Completions::class)]
#[UsesClass(OpenAiResultConverter::class)]
#[Small]
class ResultConverterTest extends TestCase
{
    public function testItSupportsCompletionsModel()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new Completions('test-model')));
    }
}
