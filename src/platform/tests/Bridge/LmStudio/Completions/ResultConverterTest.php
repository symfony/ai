<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\LmStudio\Completions;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\LmStudio\Completions;
use Symfony\AI\Platform\Bridge\LmStudio\Completions\ResultConverter;

class ResultConverterTest extends TestCase
{
    public function testItSupportsCompletionsModel()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new Completions('test-model')));
    }
}
