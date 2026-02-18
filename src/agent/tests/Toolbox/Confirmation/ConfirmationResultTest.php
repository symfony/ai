<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox\Confirmation;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\Confirmation\ConfirmationResult;

final class ConfirmationResultTest extends TestCase
{
    public function testConfirmed()
    {
        $result = ConfirmationResult::confirmed();

        $this->assertTrue($result->isConfirmed());
        $this->assertFalse($result->shouldRemember());
    }

    public function testDenied()
    {
        $result = ConfirmationResult::denied();

        $this->assertFalse($result->isConfirmed());
        $this->assertFalse($result->shouldRemember());
    }

    public function testAlways()
    {
        $result = ConfirmationResult::always();

        $this->assertTrue($result->isConfirmed());
        $this->assertTrue($result->shouldRemember());
    }

    public function testNever()
    {
        $result = ConfirmationResult::never();

        $this->assertFalse($result->isConfirmed());
        $this->assertTrue($result->shouldRemember());
    }
}
