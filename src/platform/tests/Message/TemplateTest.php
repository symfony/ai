<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Message;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Message\Template;

final class TemplateTest extends TestCase
{
    public function testConstructor()
    {
        $template = new Template('Hello {name}', 'string');

        $this->assertSame('Hello {name}', $template->getTemplate());
        $this->assertSame('string', $template->getType());
    }

    public function testStringable()
    {
        $template = new Template('Hello {name}', 'string');

        $this->assertSame('Hello {name}', (string) $template);
    }

    public function testStringNamedConstructor()
    {
        $template = Template::string('Hello {name}');

        $this->assertSame('Hello {name}', $template->getTemplate());
        $this->assertSame('string', $template->getType());
    }

    public function testExpressionNamedConstructor()
    {
        $template = Template::expression('Total: {price * quantity}');

        $this->assertSame('Total: {price * quantity}', $template->getTemplate());
        $this->assertSame('expression', $template->getType());
    }
}
