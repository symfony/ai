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
use Symfony\AI\Agent\InputProcessor\ModelRouterInputProcessor;
use Symfony\AI\Agent\Router\Result\RoutingResult;
use Symfony\AI\Agent\Router\RouterContext;
use Symfony\AI\Agent\Router\RouterInterface;
use Symfony\AI\Agent\Router\SimpleRouter;
use Symfony\AI\Agent\Router\Transformer\TransformerInterface;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;

final class ModelRouterInputProcessorTest extends TestCase
{
    public function testProcessesInputAndChangesModel(): void
    {
        $router = new SimpleRouter(
            fn (Input $input, RouterContext $context) => new RoutingResult('gpt-4-vision')
        );

        $processor = new ModelRouterInputProcessor($router);

        $platform = $this->createMock(PlatformInterface::class);

        $input = new Input(
            'gpt-4',
            new MessageBag(Message::ofUser('test')),
            [],
            $platform
        );

        $processor->processInput($input);

        $this->assertSame('gpt-4-vision', $input->getModel());
    }

    public function testDoesNothingWhenRouterReturnsNull(): void
    {
        $router = new SimpleRouter(
            fn () => null
        );

        $processor = new ModelRouterInputProcessor($router);

        $platform = $this->createMock(PlatformInterface::class);

        $input = new Input(
            'gpt-4',
            new MessageBag(Message::ofUser('test')),
            [],
            $platform
        );

        $processor->processInput($input);

        $this->assertSame('gpt-4', $input->getModel());
    }

    public function testDoesNothingWhenPlatformIsNull(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->never())
            ->method('route');

        $processor = new ModelRouterInputProcessor($router);

        $input = new Input(
            'gpt-4',
            new MessageBag(Message::ofUser('test')),
            []
        );

        $processor->processInput($input);

        $this->assertSame('gpt-4', $input->getModel());
    }

    public function testAppliesTransformer(): void
    {
        $transformer = $this->createMock(TransformerInterface::class);
        $transformer->expects($this->once())
            ->method('transform')
            ->willReturnCallback(function (Input $input, RouterContext $context) {
                return new Input(
                    $input->getModel(),
                    new MessageBag(Message::ofUser('transformed')),
                    ['transformed' => true]
                );
            });

        $router = new SimpleRouter(
            fn () => new RoutingResult('gpt-4-vision', transformer: $transformer)
        );

        $processor = new ModelRouterInputProcessor($router);

        $platform = $this->createMock(PlatformInterface::class);

        $input = new Input(
            'gpt-4',
            new MessageBag(Message::ofUser('test')),
            [],
            $platform
        );

        $processor->processInput($input);

        $this->assertSame('gpt-4-vision', $input->getModel());
        $content = $input->getMessageBag()->getMessages()[0]->getContent();
        $this->assertIsArray($content);
        $this->assertInstanceOf(Text::class, $content[0]);
        $this->assertSame('transformed', $content[0]->getText());
        $this->assertSame(['transformed' => true], $input->getOptions());
    }

    public function testPassesDefaultModelInContext(): void
    {
        $capturedContext = null;

        $router = new SimpleRouter(
            function (Input $input, RouterContext $context) use (&$capturedContext) {
                $capturedContext = $context;

                return new RoutingResult('gpt-4-vision');
            }
        );

        $processor = new ModelRouterInputProcessor($router);

        $platform = $this->createMock(PlatformInterface::class);

        $input = new Input(
            'gpt-4-default',
            new MessageBag(Message::ofUser('test')),
            [],
            $platform
        );

        $processor->processInput($input);

        $this->assertInstanceOf(RouterContext::class, $capturedContext);
        $this->assertSame('gpt-4-default', $capturedContext->getDefaultModel());
    }
}
