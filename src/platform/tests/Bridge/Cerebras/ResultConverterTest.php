<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Cerebras;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Action;
use Symfony\AI\Platform\Bridge\Cerebras\Model;
use Symfony\AI\Platform\Bridge\Cerebras\ModelClient;
use Symfony\AI\Platform\Bridge\Cerebras\ResultConverter;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
#[CoversClass(ResultConverter::class)]
#[UsesClass(Model::class)]
#[Small]
class ResultConverterTest extends TestCase
{
    public function testItSupportsTheCorrectModel()
    {
        $client = new ModelClient(new MockHttpClient(), 'csk-1234567890abcdef');

        $this->assertTrue($client->supports(new Model(Model::GPT_OSS_120B), Action::CHAT));
    }
}
