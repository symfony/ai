<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Fixtures\StructuredOutput;

use Fixtures\StructuredOutput\ExampleEnum;

final class TypeInfoFixture
{
    public ?int $nullableInt = null;
    public ExampleBackedEnum $backedEnum;
    public ?ExampleBackedEnum $nullableBackedEnum = null;
    public ExampleEnum $enum;
    public ?ExampleEnum $nullableEnum = null;
    public object $payload;
}
