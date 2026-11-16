<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine\Tests;

use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;

final class SqliteSchemaTest extends TestCase
{
    use SqliteEntityManagerTrait;

    public function testLeavesNothingForTheSchemaToolToChange()
    {
        $entityManager = $this->createEntityManager();

        $sql = (new SchemaTool($entityManager))->getUpdateSchemaSql($this->schemaMetadata($entityManager));

        $this->assertSame([], $sql);
    }
}
