<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Venice\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Venice\Contract\Contract;
use Symfony\AI\Platform\Bridge\Venice\Venice;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract as PlatformContract;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

final class ContractTest extends TestCase
{
    public function testCreateReturnsPlatformContractInstance()
    {
        $contract = Contract::create();

        $this->assertInstanceOf(PlatformContract::class, $contract);
    }

    public function testCreateRegistersAudioNormalizerForAudioContent()
    {
        $contract = Contract::create();
        $model = new Venice('venice-uncensored', [Capability::INPUT_MESSAGES, Capability::INPUT_AUDIO]);

        $messages = new MessageBag(
            Message::ofUser(new Audio('fake-audio-bytes', 'audio/mpeg')),
        );

        $payload = $contract->createRequestPayload($model, $messages);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('messages', $payload);
        $this->assertIsArray($payload['messages']);

        $userMessage = $payload['messages'][0] ?? null;
        $this->assertIsArray($userMessage);
        $this->assertSame('user', $userMessage['role'] ?? null);
        $this->assertIsArray($userMessage['content'] ?? null);

        $audioContent = $userMessage['content'][0] ?? null;
        $this->assertIsArray($audioContent);
        $this->assertSame('input_audio', $audioContent['type'] ?? null);
        $this->assertIsArray($audioContent['input_audio'] ?? null);
        $this->assertArrayHasKey('data', $audioContent['input_audio']);
        $this->assertSame('mp3', $audioContent['input_audio']['format'] ?? null);
        $this->assertArrayHasKey('path', $audioContent['input_audio']);
    }

    public function testCreateAcceptsAdditionalNormalizers()
    {
        $contract = Contract::create([new PlatformContract\Normalizer\Message\Content\TextNormalizer()]);

        $this->assertInstanceOf(PlatformContract::class, $contract);
    }
}
