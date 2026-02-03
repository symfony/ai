<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Router;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\Router\Result\RoutingResult;
use Symfony\AI\Agent\Router\RouterContext;
use Symfony\AI\Agent\Router\SimpleRouter;
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;

final class SimpleRouterTest extends TestCase
{
    public function testRoutesBasedOnCallableLogic(): void
    {
        $router = new SimpleRouter(
            fn (Input $input, RouterContext $context) => $input->getMessageBag()->containsImage()
                    ? new RoutingResult('gpt-4-vision')
                    : null
        );

        $platform = $this->createMock(PlatformInterface::class);
        $context = new RouterContext($platform);

        // Test with image
        $inputWithImage = new Input(
            'gpt-4',
            new MessageBag(Message::ofUser('test', new ImageUrl(__FILE__))),
            []
        );
        $result = $router->route($inputWithImage, $context);
        $this->assertInstanceOf(RoutingResult::class, $result);
        $this->assertSame('gpt-4-vision', $result->getModelName());

        // Test without image
        $inputWithoutImage = new Input(
            'gpt-4',
            new MessageBag(Message::ofUser('test')),
            []
        );
        $result = $router->route($inputWithoutImage, $context);
        $this->assertNull($result);
    }

    public function testCanAccessContextInCallable(): void
    {
        $router = new SimpleRouter(
            fn (Input $input, RouterContext $context) => new RoutingResult($context->getDefaultModel() ?? 'fallback')
        );

        $platform = $this->createMock(PlatformInterface::class);
        $context = new RouterContext($platform, metadata: ['default_model' => 'gpt-4']);

        $input = new Input(
            'gpt-4',
            new MessageBag(Message::ofUser('test')),
            []
        );

        $result = $router->route($input, $context);
        $this->assertInstanceOf(RoutingResult::class, $result);
        $this->assertSame('gpt-4', $result->getModelName());
    }

    public function testCanReturnNull(): void
    {
        $router = new SimpleRouter(
            fn (Input $input, RouterContext $context) => null
        );

        $platform = $this->createMock(PlatformInterface::class);
        $context = new RouterContext($platform);

        $input = new Input(
            'gpt-4',
            new MessageBag(Message::ofUser('test')),
            []
        );

        $result = $router->route($input, $context);
        $this->assertNull($result);
    }

    public function testCanIncludeReasonAndConfidence(): void
    {
        $router = new SimpleRouter(
            fn () => new RoutingResult(
                'gpt-4-vision',
                reason: 'Vision model selected',
                confidence: 95
            )
        );

        $platform = $this->createMock(PlatformInterface::class);
        $context = new RouterContext($platform);

        $input = new Input(
            'gpt-4',
            new MessageBag(Message::ofUser('test')),
            []
        );

        $result = $router->route($input, $context);
        $this->assertInstanceOf(RoutingResult::class, $result);
        $this->assertSame('gpt-4-vision', $result->getModelName());
        $this->assertSame('Vision model selected', $result->getReason());
        $this->assertSame(95, $result->getConfidence());
    }
}
