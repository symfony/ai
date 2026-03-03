<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Profiler\Service\Formatter;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\MailerCollectorFormatter;
use Symfony\Component\Mailer\DataCollector\MessageDataCollector;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Event\MessageEvents;
use Symfony\Component\Mailer\EventListener\MessageLoggerListener;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class MailerCollectorFormatterTest extends TestCase
{
    private MailerCollectorFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new MailerCollectorFormatter();
    }

    public function testGetName()
    {
        $this->assertSame('mailer', $this->formatter->getName());
    }

    public function testFormatWithNoMessages()
    {
        $collector = $this->createCollectorWithMessages([]);

        $result = $this->formatter->format($collector);

        $this->assertSame(0, $result['message_count']);
        $this->assertSame([], $result['messages']);
    }

    public function testFormatWithSingleEmail()
    {
        $email = (new Email())
            ->from(new Address('sender@example.com', 'John Sender'))
            ->to(new Address('recipient@example.com', 'Jane Recipient'))
            ->subject('Test Subject')
            ->text('This is the body text.');

        $collector = $this->createCollectorWithMessages([
            $this->createMessageEvent($email, 'smtp', false),
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame(1, $result['message_count']);
        $this->assertCount(1, $result['messages']);

        $message = $result['messages'][0];
        $this->assertSame('smtp', $message['transport']);
        $this->assertFalse($message['is_queued']);
        $this->assertSame('Test Subject', $message['subject']);
        $this->assertSame(['John Sender <sender@example.com>'], $message['from']);
        $this->assertSame(['Jane Recipient <recipient@example.com>'], $message['to']);
        $this->assertSame([], $message['cc']);
        $this->assertSame([], $message['bcc']);
        $this->assertSame([], $message['reply_to']);
        $this->assertSame('This is the body text.', $message['text_body']);
        $this->assertFalse($message['has_html_body']);
        $this->assertSame([], $message['attachments']);
    }

    public function testFormatWithQueuedEmail()
    {
        $email = (new Email())
            ->from('sender@example.com')
            ->to('recipient@example.com')
            ->subject('Queued Email');

        $collector = $this->createCollectorWithMessages([
            $this->createMessageEvent($email, 'async', true),
        ]);

        $result = $this->formatter->format($collector);

        $this->assertTrue($result['messages'][0]['is_queued']);
        $this->assertSame('async', $result['messages'][0]['transport']);
    }

    public function testFormatWithHtmlBody()
    {
        $email = (new Email())
            ->from('sender@example.com')
            ->to('recipient@example.com')
            ->subject('HTML Email')
            ->html('<h1>Hello World</h1>');

        $collector = $this->createCollectorWithMessages([
            $this->createMessageEvent($email, 'smtp', false),
        ]);

        $result = $this->formatter->format($collector);

        $this->assertNull($result['messages'][0]['text_body']);
        $this->assertTrue($result['messages'][0]['has_html_body']);
    }

    public function testFormatWithAllRecipientTypes()
    {
        $email = (new Email())
            ->from('sender@example.com')
            ->to('to@example.com')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->replyTo('reply@example.com')
            ->subject('Full Recipients');

        $collector = $this->createCollectorWithMessages([
            $this->createMessageEvent($email, 'smtp', false),
        ]);

        $result = $this->formatter->format($collector);

        $message = $result['messages'][0];
        $this->assertSame(['sender@example.com'], $message['from']);
        $this->assertSame(['to@example.com'], $message['to']);
        $this->assertSame(['cc@example.com'], $message['cc']);
        $this->assertSame(['bcc@example.com'], $message['bcc']);
        $this->assertSame(['reply@example.com'], $message['reply_to']);
    }

    public function testFormatTruncatesLongBody()
    {
        $longBody = str_repeat('a', 600);
        $email = (new Email())
            ->from('sender@example.com')
            ->to('recipient@example.com')
            ->subject('Long Body')
            ->text($longBody);

        $collector = $this->createCollectorWithMessages([
            $this->createMessageEvent($email, 'smtp', false),
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame(503, mb_strlen($result['messages'][0]['text_body']));
        $this->assertStringEndsWith('...', $result['messages'][0]['text_body']);
    }

    public function testFormatWithAttachments()
    {
        $email = (new Email())
            ->from('sender@example.com')
            ->to('recipient@example.com')
            ->subject('With Attachment')
            ->text('See attachment')
            ->attach('file content', 'document.pdf', 'application/pdf');

        $collector = $this->createCollectorWithMessages([
            $this->createMessageEvent($email, 'smtp', false),
        ]);

        $result = $this->formatter->format($collector);

        $this->assertCount(1, $result['messages'][0]['attachments']);
        $this->assertSame('document.pdf', $result['messages'][0]['attachments'][0]['filename']);
        $this->assertSame('application/pdf', $result['messages'][0]['attachments'][0]['content_type']);
    }

    public function testFormatExtractsLinksFromBody()
    {
        $email = (new Email())
            ->from('sender@example.com')
            ->to('recipient@example.com')
            ->subject('Email with links')
            ->text("Visit https://example.com and https://symfony.com for more info.\nSee also https://example.com again.");

        $collector = $this->createCollectorWithMessages([
            $this->createMessageEvent($email, 'smtp', false),
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame(
            ['https://example.com', 'https://symfony.com'],
            $result['messages'][0]['links']
        );
    }

    public function testFormatExtractsLinksFromHtmlBody()
    {
        $email = (new Email())
            ->from('sender@example.com')
            ->to('recipient@example.com')
            ->subject('HTML only')
            ->html('<a href="https://example.com">link</a> and <a href="https://symfony.com">another</a>');

        $collector = $this->createCollectorWithMessages([
            $this->createMessageEvent($email, 'smtp', false),
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame(
            ['https://example.com', 'https://symfony.com'],
            $result['messages'][0]['links']
        );
    }

    public function testFormatDeduplicatesLinksAcrossTextAndHtmlBody()
    {
        $email = (new Email())
            ->from('sender@example.com')
            ->to('recipient@example.com')
            ->subject('Both bodies')
            ->text('Visit https://example.com for info.')
            ->html('<a href="https://example.com">link</a> <a href="https://symfony.com">other</a>');

        $collector = $this->createCollectorWithMessages([
            $this->createMessageEvent($email, 'smtp', false),
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame(
            ['https://example.com', 'https://symfony.com'],
            $result['messages'][0]['links']
        );
    }

    public function testFormatWithRawMessage()
    {
        $rawMessage = new RawMessage('raw email content');

        $collector = $this->createCollectorWithMessages([
            $this->createMessageEvent($rawMessage, 'smtp', false),
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame(1, $result['message_count']);
        $this->assertSame('smtp', $result['messages'][0]['transport']);
        $this->assertFalse($result['messages'][0]['is_queued']);
        $this->assertSame(RawMessage::class, $result['messages'][0]['type']);
    }

    public function testFormatWithMultipleMessages()
    {
        $email1 = (new Email())
            ->from('sender@example.com')
            ->to('recipient1@example.com')
            ->subject('First Email');

        $email2 = (new Email())
            ->from('sender@example.com')
            ->to('recipient2@example.com')
            ->subject('Second Email');

        $collector = $this->createCollectorWithMessages([
            $this->createMessageEvent($email1, 'smtp', false),
            $this->createMessageEvent($email2, 'async', true),
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame(2, $result['message_count']);
        $this->assertSame('First Email', $result['messages'][0]['subject']);
        $this->assertSame('Second Email', $result['messages'][1]['subject']);
    }

    public function testGetSummaryWithNoMessages()
    {
        $collector = $this->createCollectorWithMessages([]);

        $result = $this->formatter->getSummary($collector);

        $this->assertSame(0, $result['message_count']);
        $this->assertSame([], $result['subjects']);
    }

    public function testGetSummaryWithMessages()
    {
        $email1 = (new Email())
            ->from('sender@example.com')
            ->to('recipient@example.com')
            ->subject('Welcome Email');

        $email2 = (new Email())
            ->from('sender@example.com')
            ->to('recipient@example.com')
            ->subject('Password Reset');

        $collector = $this->createCollectorWithMessages([
            $this->createMessageEvent($email1, 'smtp', false),
            $this->createMessageEvent($email2, 'smtp', false),
        ]);

        $result = $this->formatter->getSummary($collector);

        $this->assertSame(2, $result['message_count']);
        $this->assertSame(['Welcome Email', 'Password Reset'], $result['subjects']);
    }

    public function testGetSummaryWithNoSubject()
    {
        $email = (new Email())
            ->from('sender@example.com')
            ->to('recipient@example.com');

        $collector = $this->createCollectorWithMessages([
            $this->createMessageEvent($email, 'smtp', false),
        ]);

        $result = $this->formatter->getSummary($collector);

        $this->assertSame(['(no subject)'], $result['subjects']);
    }

    public function testGetSummaryExcludesRawMessages()
    {
        $email = (new Email())
            ->from('sender@example.com')
            ->to('recipient@example.com')
            ->subject('Regular Email');

        $rawMessage = new RawMessage('raw content');

        $collector = $this->createCollectorWithMessages([
            $this->createMessageEvent($email, 'smtp', false),
            $this->createMessageEvent($rawMessage, 'smtp', false),
        ]);

        $result = $this->formatter->getSummary($collector);

        $this->assertSame(2, $result['message_count']);
        $this->assertSame(['Regular Email'], $result['subjects']);
    }

    /**
     * @param MessageEvent[] $events
     */
    private function createCollectorWithMessages(array $events): MessageDataCollector
    {
        $messageEvents = new MessageEvents();
        foreach ($events as $event) {
            $messageEvents->add($event);
        }

        $logger = $this->createMock(MessageLoggerListener::class);
        $logger->method('getEvents')->willReturn($messageEvents);

        $collector = new MessageDataCollector($logger);
        $collector->collect(
            new \Symfony\Component\HttpFoundation\Request(),
            new \Symfony\Component\HttpFoundation\Response()
        );

        return $collector;
    }

    private function createMessageEvent(RawMessage $message, string $transport, bool $queued): MessageEvent
    {
        $envelope = new Envelope(
            new Address('sender@example.com'),
            [new Address('recipient@example.com')]
        );

        return new MessageEvent($message, $envelope, $transport, $queued);
    }
}
