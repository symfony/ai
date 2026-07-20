<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Fireworks\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Fireworks\Fireworks;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class FireworksTest extends TestCase
{
    public function testItCreatesFireworksWithDefaultSettings()
    {
        $model = new Fireworks('accounts/fireworks/models/kimi-k2p6');

        $this->assertSame('accounts/fireworks/models/kimi-k2p6', $model->getName());
        $this->assertSame([], $model->getOptions());
    }

    public function testItCreatesFireworksWithCustomSettings()
    {
        $model = new Fireworks('accounts/fireworks/models/kimi-k2p6', [], ['temperature' => 0.5]);

        $this->assertSame('accounts/fireworks/models/kimi-k2p6', $model->getName());
        $this->assertSame(['temperature' => 0.5], $model->getOptions());
    }
}
