<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Acp\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Acp\Acp;
use Symfony\AI\Platform\Bridge\Acp\ModelCatalog;
use Symfony\AI\Platform\Capability;

/**
 * @covers \Symfony\AI\Platform\Bridge\Acp\ModelCatalog
 */
final class ModelCatalogTest extends TestCase
{
    public function testGetModelReturnsAcpModelWithCorrectCapabilities()
    {
        $catalog = new ModelCatalog();

        $model = $catalog->getModel('acp-v1');

        $this->assertInstanceOf(Acp::class, $model);
        $this->assertTrue($model->supports(Capability::INPUT_TEXT));
        $this->assertTrue($model->supports(Capability::INPUT_MESSAGES));
        $this->assertTrue($model->supports(Capability::OUTPUT_TEXT));
        $this->assertFalse($model->supports(Capability::INPUT_IMAGE));
        $this->assertSame([], $model->clientCapabilities);
        $this->assertSame([], $model->requiredAgentCapabilities);
        $this->assertSame(1, $model->protocolVersion);
    }

    public function testGetModelAcpV2SetsProtocolVersion()
    {
        $catalog = new ModelCatalog();

        $model = $catalog->getModel('acp-v2');

        $this->assertInstanceOf(Acp::class, $model);
        $this->assertSame(2, $model->protocolVersion);
    }

    public function testGetModelSetsClientCapabilitiesFromCatalog()
    {
        $catalog = new ModelCatalog();

        $model = $catalog->getModel('acp-v1');

        $this->assertInstanceOf(Acp::class, $model);
        $this->assertSame([], $model->clientCapabilities);
        $this->assertSame([], $model->requiredAgentCapabilities);
    }
}
