<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Realtime;
use Symfony\AI\Platform\Capability;

/**
 * @author Saiful Islam Feroz <saiful.feroz@gmail.com>
 */
final class RealtimeTest extends TestCase
{
    public function testRealtimeModelDefaults()
    {
        $model = new Realtime('gpt-4o-realtime-preview');

        $this->assertSame('gpt-4o-realtime-preview', $model->getName());
        $this->assertTrue($model->supports(Capability::REALTIME_SESSION));
    }
}
