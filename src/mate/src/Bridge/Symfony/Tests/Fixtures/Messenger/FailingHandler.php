<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Fixtures\Messenger;

/**
 * Throws from distinct lines, so failures group by the frame that threw.
 */
final class FailingHandler
{
    public static function writeFile(string $path): \Throwable
    {
        return self::catch(static fn () => throw new \RuntimeException(\sprintf('Could not write "%s": No such file or directory', $path)));
    }

    public static function parsePrice(string $price): \Throwable
    {
        return self::catch(static fn () => throw new \InvalidArgumentException(\sprintf('"%s" is not a decimal amount.', $price)));
    }

    public static function parsePriceElsewhere(string $price): \Throwable
    {
        return self::catch(static fn () => throw new \InvalidArgumentException(\sprintf('"%s" is not a decimal amount.', $price)));
    }

    public static function fail(string $message): \Throwable
    {
        return self::catch(static fn () => throw new \RuntimeException($message));
    }

    private static function catch(\Closure $code): \Throwable
    {
        try {
            $code();
        } catch (\Throwable $e) {
            return $e;
        }

        throw new \LogicException('Expected an exception.');
    }
}
