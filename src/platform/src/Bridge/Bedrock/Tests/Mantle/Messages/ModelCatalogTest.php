<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Tests\Mantle\Messages;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Bedrock\Mantle\Messages\ModelCatalog;
use Symfony\AI\Platform\Capability;

/**
 * @author asrar <aszenz@gmail.com>
 */
final class ModelCatalogTest extends TestCase
{
    /**
     * @param list<Capability> $capabilities
     */
    #[DataProvider('provideModels')]
    public function testItReturnsClaudeModels(string $modelName, array $capabilities)
    {
        $model = (new ModelCatalog())->getModel($modelName);

        $this->assertInstanceOf(Claude::class, $model);
        $this->assertSame($modelName, $model->getName());
        $this->assertSame($capabilities, $model->getCapabilities());
        $this->assertFalse($model->supports(Capability::OUTPUT_STRUCTURED));
    }

    /**
     * @return iterable<string, array{string, list<Capability>}>
     */
    public static function provideModels(): iterable
    {
        $capabilities = [
            Capability::INPUT_MESSAGES,
            Capability::INPUT_IMAGE,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::THINKING,
            Capability::TOOL_CALLING,
        ];

        yield 'Claude Haiku 4.5' => ['anthropic.claude-haiku-4-5', $capabilities];
        yield 'Claude Opus 4.7' => ['anthropic.claude-opus-4-7', $capabilities];
        yield 'Claude Opus 4.8' => ['anthropic.claude-opus-4-8', $capabilities];
        yield 'Claude Opus 5' => ['anthropic.claude-opus-5', $capabilities];
        yield 'Claude Sonnet 5' => ['anthropic.claude-sonnet-5', $capabilities];
    }
}
