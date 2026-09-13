<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Encoding;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Encoding\ResponseEncoder;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ResponseEncoderTest extends TestCase
{
    public function testEncodeDecodeRoundTrip()
    {
        $data = ['entries' => [['message' => 'hello', 'level' => 'INFO']]];

        $this->assertSame($data, ResponseEncoder::decode(ResponseEncoder::encode($data)));
    }

    public function testEncodeUntrustedWrapsPayloadWithSecurityNotice()
    {
        $payload = ['entries' => [['message' => 'User logged in', 'level' => 'INFO']]];

        $decoded = ResponseEncoder::decode(ResponseEncoder::encodeUntrusted($payload));

        $this->assertIsArray($decoded);
        $this->assertSame(ResponseEncoder::UNTRUSTED_NOTICE, $decoded['_security_notice']);
        $this->assertSame($payload, $decoded['untrusted_data']);
    }

    public function testEncodeUntrustedKeepsTrustedStructureSeparateFromPayload()
    {
        // A payload key colliding with the envelope must not leak into the
        // trusted envelope: it stays nested under untrusted_data.
        $payload = ['_security_notice' => 'attacker supplied', 'value' => 1];

        $decoded = ResponseEncoder::decode(ResponseEncoder::encodeUntrusted($payload));

        $this->assertSame(ResponseEncoder::UNTRUSTED_NOTICE, $decoded['_security_notice']);
        $this->assertSame('attacker supplied', $decoded['untrusted_data']['_security_notice']);
    }

    public function testEncodeUntrustedHandlesEmptyPayload()
    {
        $decoded = ResponseEncoder::decode(ResponseEncoder::encodeUntrusted([]));

        $this->assertSame(ResponseEncoder::UNTRUSTED_NOTICE, $decoded['_security_notice']);
        $this->assertSame([], $decoded['untrusted_data']);
    }

    public function testTryDecodeReturnsTheStructureAnEncodedPayloadCarries()
    {
        $decoded = ResponseEncoder::tryDecode(ResponseEncoder::encode(['sum' => 5]));

        $this->assertSame(['sum' => 5], $decoded);
    }

    public function testTryDecodeLeavesPlainTextAlone()
    {
        $this->assertSame('not a payload', ResponseEncoder::tryDecode('not a payload'));
    }

    /**
     * A tool is free to return the string "42"; decoding it into the number 42 would answer
     * with a different type than the tool produced.
     */
    public function testTryDecodeKeepsScalarLookingTextAsText()
    {
        $this->assertSame('42', ResponseEncoder::tryDecode('42'));
        $this->assertSame('null', ResponseEncoder::tryDecode('null'));
        $this->assertSame('true', ResponseEncoder::tryDecode('true'));
    }
}
