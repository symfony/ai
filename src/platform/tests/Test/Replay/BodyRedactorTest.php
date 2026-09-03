<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Test\Replay;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Test\Replay\BodyRedactor;

final class BodyRedactorTest extends TestCase
{
    #[Test]
    #[DataProvider('provideCredentials')]
    public function itRedactsCredentials(string $input, string $expected): void
    {
        $this->assertSame($expected, (new BodyRedactor())->redact($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideCredentials(): iterable
    {
        yield 'openai key' => ['use sk-proj-AbCdEf0123456789XyZwQrSt now', 'use [redacted-key] now'];
        yield 'groq key' => ['gsk_aB3dEf5hIj7kLm9nOp1qRs3tUv5w', '[redacted-key]'];
        yield 'google key' => ['AIzaSyD-1234567890abcdefghijklmnopqrs', '[redacted-key]'];
        yield 'aws access key' => ['AKIAIOSFODNN7EXAMPLE', '[redacted-key]'];
        yield 'github token' => ['ghp_1234567890abcdefghijklmnopqrstuvwx', '[redacted-key]'];
        yield 'jwt' => [
            'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.dozjgNryP4J3jVmNHl0w5N_XgL0n3I9PlFUP0THsR8U',
            '[redacted-jwt]',
        ];
        yield 'bearer token' => ['Authorization: Bearer abcdefghijklmnopqrstuvwxyz123', 'Authorization: Bearer [redacted]'];
    }

    #[Test]
    #[DataProvider('providePersonalData')]
    public function itRedactsPersonalDataByDefault(string $input, string $expected): void
    {
        $this->assertSame($expected, (new BodyRedactor())->redact($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providePersonalData(): iterable
    {
        yield 'email' => ['write to user@example.com please', 'write to [redacted-email] please'];
        yield 'international phone' => ['call +34 686 762 644 today', 'call [redacted-phone] today'];
        yield 'us phone' => ['call +1 (555) 123-4567 today', 'call [redacted-phone] today'];
    }

    /**
     * A cassette is meant to be reviewable. An unanchored pattern that eats timestamps, token
     * counts or identifiers out of a legitimate payload corrupts the very recording it was
     * supposed to protect.
     */
    #[Test]
    #[DataProvider('provideLegitimatePayloads')]
    public function itLeavesLegitimateValuesUntouched(string $input): void
    {
        $this->assertSame($input, (new BodyRedactor())->redact($input));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideLegitimatePayloads(): iterable
    {
        yield 'iso timestamp' => ['2026-08-25T14:30:00Z'];
        yield 'unix timestamp' => ['created_at 1756132200'];
        yield 'order number and amount' => ['order 4417 total 89.90'];
        yield 'uuid' => ['550e8400-e29b-41d4-a716-446655440000'];
        yield 'model name' => ['llama-3.1-8b-instant'];
        yield 'token counts' => ['input 115 output 26'];
        yield 'hash' => ['sha 9a89bea9cc915e9e1164167c'];
    }

    #[Test]
    public function itWalksNestedArraysWithoutTouchingKeysOrScalars(): void
    {
        $redacted = (new BodyRedactor())->redact([
            'messages' => [
                ['role' => 'user', 'content' => 'I am a@b.com, key sk-proj-AbCdEf0123456789XyZ'],
            ],
            'temperature' => 0.0,
            'max_tokens' => 512,
        ]);

        $this->assertSame('I am [redacted-email], key [redacted-key]', $redacted['messages'][0]['content']);
        $this->assertSame('user', $redacted['messages'][0]['role']);
        $this->assertSame(0.0, $redacted['temperature']);
        $this->assertSame(512, $redacted['max_tokens']);
    }

    #[Test]
    public function itKeepsPersonalDataWhenItIsTheThingUnderTest(): void
    {
        $redactor = BodyRedactor::credentialsOnly();

        $this->assertSame('a@b.com', $redactor->redact('a@b.com'));
        $this->assertSame('[redacted-key]', $redactor->redact('sk-proj-AbCdEf0123456789XyZ'));
    }

    #[Test]
    public function itAppliesUserSuppliedPatternsOnTopOfTheDefaults(): void
    {
        $redactor = new BodyRedactor(extraPatterns: ['/\bCUST-\d{5}\b/' => '[redacted-customer]']);

        $this->assertSame(
            '[redacted-customer] sk [redacted-key]',
            $redactor->redact('CUST-12345 sk sk-proj-AbCdEf0123456789XyZ'),
        );
    }

    #[Test]
    public function itRejectsAnInvalidPattern(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a valid regular expression');

        new BodyRedactor(extraPatterns: ['/[unclosed/' => 'x']);
    }

    #[Test]
    public function itLeavesNonStringScalarsAlone(): void
    {
        $redactor = new BodyRedactor();

        $this->assertNull($redactor->redact(null));
        $this->assertSame(42, $redactor->redact(42));
        $this->assertTrue($redactor->redact(true));
    }
}
