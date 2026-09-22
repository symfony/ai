<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Supabase\Tests;

use PHPUnit\Framework\Attributes\Group;
use Symfony\AI\Store\Bridge\Supabase\StoreFactory;
use Symfony\AI\Store\StoreInterface;
use Symfony\AI\Store\Test\AbstractStoreIntegrationTestCase;

/**
 * The Supabase store does not implement {@see \Symfony\AI\Store\ManagedStoreInterface}: its schema is
 * provisioned via the init.sql fixture, matching how Supabase users manage schemas through migrations
 * rather than through the REST API. The shared test case skips setup() and drop() accordingly.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[Group('integration')]
final class IntegrationTest extends AbstractStoreIntegrationTestCase
{
    protected static function createStore(): StoreInterface
    {
        return StoreFactory::create(
            endpoint: 'http://127.0.0.1:3000',
            table: 'documents',
            vectorFieldName: 'embedding',
            vectorDimension: 3,
            functionName: 'match_documents',
        );
    }
}
