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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Symfony\Messenger\FailedMessage;
use Symfony\AI\Mate\Bridge\Symfony\Messenger\FailedMessageGrouper;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class FailedMessageGrouperTest extends TestCase
{
    public function testKeepsStatusCodesApartAndBlanksIds()
    {
        $grouper = new FailedMessageGrouper();

        $this->assertNotSame($grouper->pattern('HTTP 500 returned by the ERP'), $grouper->pattern('HTTP 404 returned by the ERP'));
        $this->assertSame($grouper->pattern('Order 10231 not found'), $grouper->pattern('Order 99812 not found'));
        $this->assertSame($grouper->pattern('Price "12,50" is invalid'), $grouper->pattern('Price "9,63" is invalid'));
        $this->assertSame($grouper->pattern('Amount 12.50 exceeds the limit'), $grouper->pattern('Amount 9.63 exceeds the limit'));
    }

    public function testBlanksEveryNumberExceptAnHttpStatus()
    {
        $grouper = new FailedMessageGrouper();

        $this->assertSame($grouper->pattern('Order 17 not found'), $grouper->pattern('Order 18 not found'));
        $this->assertSame($grouper->pattern('Retry 3 of 5 failed'), $grouper->pattern('Retry 4 of 5 failed'));
        $this->assertNotSame($grouper->pattern('HTTP 500 returned'), $grouper->pattern('HTTP 404 returned'));
        $this->assertNotSame($grouper->pattern('Server answered with status 503'), $grouper->pattern('Server answered with status 502'));
        $this->assertSame($grouper->pattern('Exit code 12'), $grouper->pattern('Exit code 13'));
    }

    public function testMasksSecretsInMessages()
    {
        $grouper = new FailedMessageGrouper();

        $this->assertSame('Invalid token ***', $grouper->mask('Invalid token eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.c2lnbmF0dXJl'));
        $this->assertSame('Authorization: ***', $grouper->mask('Authorization: Bearer abc123def'));
        $this->assertSame('Rejected Bearer ***', $grouper->mask('Rejected Bearer abc123def'));
        $this->assertSame('Connect to mysql://***@db:3306/app failed', $grouper->mask('Connect to mysql://root:s3cret@db:3306/app failed'));
        $this->assertSame('password=*** rejected', $grouper->mask('password=hunter2 rejected'));
        $this->assertSame('Could not find App\\Service\\SomeVeryLongClassNameHandler', $grouper->mask('Could not find App\\Service\\SomeVeryLongClassNameHandler'));
    }

    public function testStripsArgumentsFromTraceFrames()
    {
        $groups = (new FailedMessageGrouper())->group([
            new FailedMessage('1', 'App\\Message', \RuntimeException::class, 'Boom', '/app/src/Handler.php', 12, "#0 /app/src/Handler.php(20): App\\TokenHandler->verify('eyJhbGciOiJIUzI1NiJ9', Object(App\\User))\n#1 {main}", 0, null, null, null),
        ])['groups'];

        $this->assertSame('/app/src/Handler.php:20 App\\TokenHandler->verify()', $groups[0]['trace'][1]);
        $this->assertStringNotContainsString('eyJ', implode("\n", $groups[0]['trace']));
    }

    public function testCapsGroupsAfterSortingAndCountsTheOmittedOnes()
    {
        $messages = [];
        for ($i = 0; $i < 52; ++$i) {
            $messages[] = $this->message((string) $i, '/app/src/H'.$i.'.php', '');
        }
        $messages[] = $this->message('99', '/app/src/H0.php', '');

        $result = (new FailedMessageGrouper())->group($messages);

        $this->assertSame(52, $result['group_count']);
        $this->assertCount(50, $result['groups']);
        $this->assertTrue($result['groups_truncated']);
        $this->assertSame(2, $result['omitted_groups']);
        $this->assertSame(2, $result['omitted_messages']);
        $this->assertSame(2, $result['groups'][0]['count']);
    }

    public function testTellsApplicationFramesFromVendorFramesOnEveryPathStyle()
    {
        $groups = (new FailedMessageGrouper(['/home/ci/vendor/acme-shop']))->group([
            $this->message('1', '/home/ci/vendor/acme-shop/src/Handler.php', '#0 /home/ci/vendor/acme-shop/vendor/symfony/messenger/Worker.php(10): run()'),
            $this->message('2', 'C:\\app\\vendor\\symfony\\messenger\\Worker.php', '#0 C:\\app\\src\\Handler.php(20): App\\Handler->__invoke()'),
        ])['groups'];

        $this->assertSame(['src/Handler.php:12', 'C:\\app\\src\\Handler.php:20'], array_column($groups, 'failed_in'));
    }

    private function message(string $id, string $file, string $trace): FailedMessage
    {
        return new FailedMessage($id, 'App\\Message', \RuntimeException::class, 'Boom '.$id, $file, 12, $trace, 0, null, null, null);
    }
}
