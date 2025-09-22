<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\OpenAi;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\DallE;

final class DallETest extends TestCase
{
    public function testItCreatesDallEWithDefaultSettings()
    {
        $dallE = new DallE('dall-e-2');

        $this->assertSame('dall-e-2', $dallE->getName());
        $this->assertSame([], $dallE->getOptions());
    }

    public function testItCreatesDallEWithCustomSettings()
    {
        $dallE = new DallE('dall-e-3', options: ['response_format' => 'base64', 'n' => 2]);

        $this->assertSame('dall-e-3', $dallE->getName());
        $this->assertSame(['response_format' => 'base64', 'n' => 2], $dallE->getOptions());
    }
}
