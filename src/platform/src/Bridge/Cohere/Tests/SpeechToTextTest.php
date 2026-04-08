<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Cohere\SpeechToText;
use Symfony\AI\Platform\Model;

final class SpeechToTextTest extends TestCase
{
    public function testItExtendsModel()
    {
        $model = new SpeechToText('cohere-transcribe-03-2026');

        $this->assertInstanceOf(Model::class, $model);
        $this->assertSame('cohere-transcribe-03-2026', $model->getName());
    }
}
