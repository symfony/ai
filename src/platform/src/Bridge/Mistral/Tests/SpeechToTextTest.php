<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Mistral\SpeechToText;
use Symfony\AI\Platform\Model;

final class SpeechToTextTest extends TestCase
{
    public function testItExtendsModel()
    {
        $model = new SpeechToText('voxtral-mini-latest');

        $this->assertInstanceOf(Model::class, $model);
        $this->assertSame('voxtral-mini-latest', $model->getName());
    }
}
