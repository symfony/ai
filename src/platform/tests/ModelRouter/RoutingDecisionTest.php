<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\ModelRouter;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelRouter\RoutingDecision;
use Symfony\AI\Platform\ProviderInterface;

final class RoutingDecisionTest extends TestCase
{
    public function testProviderOnlyDecisionKeepsRequestedModel()
    {
        $provider = $this->createStub(ProviderInterface::class);

        $decision = new RoutingDecision($provider);

        $this->assertSame($provider, $decision->getProvider());
        $this->assertNull($decision->getModel());
        $this->assertNull($decision->getOptions());
        $this->assertSame('', $decision->getReason());
    }

    public function testDecisionCanSelectModelByName()
    {
        $provider = $this->createStub(ProviderInterface::class);

        $decision = new RoutingDecision($provider, 'gpt-4o', reason: 'image detected');

        $this->assertSame('gpt-4o', $decision->getModel());
        $this->assertSame('image detected', $decision->getReason());
    }

    public function testDecisionCanReplaceOptions()
    {
        $provider = $this->createStub(ProviderInterface::class);

        $decision = new RoutingDecision($provider, 'gpt-4o', ['max_tokens' => 100], 'token limit rewritten');

        $this->assertSame(['max_tokens' => 100], $decision->getOptions());
        $this->assertSame('token limit rewritten', $decision->getReason());
    }

    public function testDecisionCanSelectModelInstance()
    {
        $provider = $this->createStub(ProviderInterface::class);
        $model = new Model('gpt-4o', [], ['temperature' => 0.2]);

        $decision = new RoutingDecision($provider, $model);

        $this->assertSame($model, $decision->getModel());
    }
}
