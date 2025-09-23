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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\DallE;

#[CoversClass(DallE::class)]
#[Small]
final class DallETest extends TestCase
{
    public function testItCreatesDallEWithDefaultSettings()
    {
        $dallE = new DallE(DallE::DALL_E_2);

        $this->assertSame(DallE::DALL_E_2, $dallE->getName());
        $this->assertSame([], $dallE->getOptions());
    }

    public function testItCreatesDallEWithCustomSettings()
    {
        $dallE = new DallE(DallE::DALL_E_3, ['response_format' => 'base64', 'n' => 2]);

        $this->assertSame(DallE::DALL_E_3, $dallE->getName());
        $this->assertSame(['response_format' => 'base64', 'n' => 2], $dallE->getOptions());
    }
}
