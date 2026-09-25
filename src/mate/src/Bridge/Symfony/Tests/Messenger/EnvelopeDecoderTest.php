<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Messenger;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Symfony\Exception\UndecodableMessageException;
use Symfony\AI\Mate\Bridge\Symfony\Messenger\EnvelopeDecoder;
use Symfony\AI\Mate\Bridge\Symfony\Tests\Fixtures\Messenger\FailingHandler;
use Symfony\AI\Mate\Bridge\Symfony\Tests\Fixtures\Messenger\Gadget;
use Symfony\AI\Mate\Bridge\Symfony\Tests\Fixtures\Messenger\ImportPrice;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Transport\Serialization\Normalizer\FlattenExceptionNormalizer;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class EnvelopeDecoderTest extends TestCase
{
    private string $marker;

    protected function setUp(): void
    {
        $this->marker = sys_get_temp_dir().'/mate_gadget_'.uniqid();
    }

    protected function tearDown(): void
    {
        if (file_exists($this->marker)) {
            unlink($this->marker);
        }
    }

    public function testReadsTheFailureDetailsOfAPhpSerializedEnvelope()
    {
        $exception = FailingHandler::parsePrice('24,56');
        $encoded = (new PhpSerializer())->encode($this->failedEnvelope($exception));

        $message = (new EnvelopeDecoder())->decode('7', $encoded['body'], $encoded['headers'] ?? [], new \DateTimeImmutable('2026-09-25 22:13:01', new \DateTimeZone('UTC')));

        $this->assertSame('7', $message->id);
        $this->assertSame(ImportPrice::class, $message->messageClass);
        $this->assertSame(\InvalidArgumentException::class, $message->exceptionClass);
        $this->assertSame('"24,56" is not a decimal amount.', $message->exceptionMessage);
        $this->assertSame($exception->getFile(), $message->file);
        $this->assertSame($exception->getLine(), $message->line);
        $this->assertStringContainsString('FailingHandler', $message->traceAsString);
        $this->assertSame(3, $message->retryCount);
        $this->assertSame('2026-09-25T22:12:30+00:00', $message->firstFailedAt?->format(\DATE_ATOM));
        $this->assertSame('2026-09-25T22:13:01+00:00', $message->lastFailedAt?->format(\DATE_ATOM));
        $this->assertSame('async', $message->originalTransport);
    }

    public function testReadsABase64EncodedPhpSerializedEnvelope()
    {
        // PhpSerializer base64-encodes a body that is not valid UTF-8.
        $encoded = (new PhpSerializer())->encode($this->failedEnvelope(FailingHandler::fail("Bad byte \xB1")));
        $this->assertStringEndsNotWith('}', $encoded['body']);

        $message = (new EnvelopeDecoder())->decode('1', $encoded['body'], [], null);

        $this->assertSame("Bad byte \xB1", $message->exceptionMessage);
        $this->assertSame(3, $message->retryCount);
    }

    /**
     * @return iterable<string, array{Serializer}>
     */
    public static function symfonySerializerProvider(): iterable
    {
        yield 'Serializer::create()' => [Serializer::create()];
        // As in a full-stack application, where the FlattenException gets its own normalizer.
        yield 'with FlattenExceptionNormalizer' => [new Serializer(new SymfonySerializer(
            [new FlattenExceptionNormalizer(), new DateTimeNormalizer(), new ArrayDenormalizer(), new ObjectNormalizer()],
            [new JsonEncoder()],
        ))];
    }

    #[DataProvider('symfonySerializerProvider')]
    public function testReadsTheStampHeadersOfTheSymfonySerializer(Serializer $serializer)
    {
        $exception = FailingHandler::writeFile('/mnt/inbox/order-1.json');
        $encoded = $serializer->encode($this->failedEnvelope($exception));
        $this->assertArrayHasKey('type', $encoded['headers']);

        $message = (new EnvelopeDecoder())->decode('9', 'not even read', $encoded['headers'], null);

        $this->assertSame(ImportPrice::class, $message->messageClass);
        $this->assertSame(\RuntimeException::class, $message->exceptionClass);
        $this->assertSame('Could not write "/mnt/inbox/order-1.json": No such file or directory', $message->exceptionMessage);
        $this->assertSame($exception->getFile(), $message->file);
        $this->assertSame($exception->getLine(), $message->line);
        $this->assertStringContainsString('FailingHandler', $message->traceAsString);
        $this->assertSame(3, $message->retryCount);
        $this->assertSame('2026-09-25T22:12:30+00:00', $message->firstFailedAt?->format(\DATE_ATOM));
        $this->assertSame('async', $message->originalTransport);
    }

    public function testNeverInstantiatesAClassFromTheStoredPayload()
    {
        // A gadget as the message and as a stamp; the envelope itself is real.
        $gadget = \sprintf('O:%d:"%s":1:{s:6:"marker";s:%d:"%s";}', \strlen(Gadget::class), Gadget::class, \strlen($this->marker), $this->marker);
        $serialized = serialize(new Envelope(new \stdClass()));
        $serialized = str_replace('O:8:"stdClass":0:{}', $gadget, $serialized);
        $serialized = str_replace('a:0:{}', 'a:1:{s:11:"App\\MyStamp";a:1:{i:0;'.$gadget.'}}', $serialized);
        $this->assertSame(2, substr_count($serialized, Gadget::class));
        $body = addslashes($serialized);

        $message = (new EnvelopeDecoder())->decode('1', $body, [], null);
        unset($message);
        gc_collect_cycles();

        $this->assertFileDoesNotExist($this->marker, 'The gadget ran while the envelope was decoded.');

        // Control: the payload is a working gadget when unserialized the way PhpSerializer does.
        $decoded = unserialize(stripslashes($body), ['allowed_classes' => true]);
        unset($decoded);
        gc_collect_cycles();
        $this->assertFileExists($this->marker);
        $this->assertSame('unserializeunserializedestructdestruct', file_get_contents($this->marker));
    }

    public function testReportsTheMessageClassOfAnIncompleteMessage()
    {
        $serialized = str_replace('O:8:"stdClass":0:{}', 'O:16:"App\\Message\\Gone":0:{}', serialize(new Envelope(new \stdClass())));

        $message = (new EnvelopeDecoder())->decode('1', addslashes($serialized), [], null);

        $this->assertSame('App\Message\Gone', $message->messageClass);
        $this->assertNull($message->exceptionClass);
        $this->assertSame(0, $message->retryCount);
    }

    /**
     * @return iterable<string, array{string, array<string, string>, string}>
     */
    public static function malformedProvider(): iterable
    {
        $valid = addslashes(serialize(new Envelope(new \stdClass())));

        yield 'plain text' => ['not a message', [], 'not a PHP-serialized Messenger envelope'];
        yield 'not base64' => ['{"price": "1"', [], 'neither a serialized envelope nor base64'];
        yield 'serialized non-envelope' => [serialize(['a' => 1]), [], 'not a PHP-serialized Messenger envelope'];
        yield 'base64 of something else' => [base64_encode('hello'), [], 'not a PHP-serialized Messenger envelope'];
        yield 'truncated envelope' => [substr($valid, 0, -3).'}', [], 'corrupt'];
        yield 'stamp header not JSON' => ['{}', ['type' => 'App\Message', 'X-Message-Stamp-Foo' => '<xml/>'], 'is not JSON'];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('malformedProvider')]
    public function testRejectsMalformedMessages(string $body, array $headers, string $reason)
    {
        $this->expectException(UndecodableMessageException::class);
        $this->expectExceptionMessage($reason);

        (new EnvelopeDecoder())->decode('1', $body, $headers, null);
    }

    private function failedEnvelope(\Throwable $exception): Envelope
    {
        return new Envelope(new ImportPrice('24,56'), [
            new RedeliveryStamp(1, new \DateTimeImmutable('2026-09-25 22:12:30', new \DateTimeZone('UTC'))),
            new RedeliveryStamp(2, new \DateTimeImmutable('2026-09-25 22:12:40', new \DateTimeZone('UTC'))),
            new RedeliveryStamp(3, new \DateTimeImmutable('2026-09-25 22:12:55', new \DateTimeZone('UTC'))),
            ErrorDetailsStamp::create($exception),
            new SentToFailureTransportStamp('async'),
        ]);
    }
}
