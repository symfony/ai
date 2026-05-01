<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Discovery\Fixtures\OutputSchema;

/**
 * Fixture tool that imports a phpstan-type from another class.
 *
 * @phpstan-import-type ItemData from ImportedTypeModel
 */
final class ImportingTool
{
    /**
     * @return array{items: list<ItemData>}
     */
    public function withImportedType(): array
    {
        return ['items' => []];
    }
}
