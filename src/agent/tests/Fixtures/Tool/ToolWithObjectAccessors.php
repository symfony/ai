<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Fixtures\Tool;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\With;

class ToolWithObjectAccessors
{
    private int $value1;
    private float $value2;

    public function __construct(
        #[With(pattern: '^foo$')]
        private string $value3,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(self $object): array
    {
        return [
            'value1' => $object->value1,
            'value2' => $object->value2,
            'value3' => $object->value3,
        ];
    }

    public function setValue1(#[With(minimum: 1)] int $value1): void
    {
        $this->value1 = $value1;
    }

    public function getValue1(): int
    {
        return $this->value1;
    }

    public function setValue2(#[With(const: 42)] float $value): void
    {
        $this->value2 = $value;
    }
}
