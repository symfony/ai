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
use Symfony\AI\Mate\Bridge\Symfony\Exception\FailureTransportNotReadableException;
use Symfony\AI\Mate\Bridge\Symfony\Messenger\ContainerConfiguration;
use Symfony\AI\Mate\Bridge\Symfony\Messenger\ProjectEnvironment;
use Symfony\AI\Mate\Bridge\Symfony\Service\ContainerProvider;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ContainerConfigurationTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/mate_container_'.uniqid();
        mkdir($this->dir.'/dev', 0777, true);
        file_put_contents($this->dir.'/dev/App_KernelDevDebugContainer.xml', <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <container xmlns="http://symfony.com/schema/dic/services">
                <parameters>
                    <parameter key="env(MATE_TEST_WITH_DEFAULT)">from-parameter</parameter>
                </parameters>
                <services/>
            </container>
            XML);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir.'/dev/App_KernelDevDebugContainer.xml');
        @rmdir($this->dir.'/dev');
        @rmdir($this->dir);
    }

    public function testUnsetEnvFallsBackToTheEnvParameter()
    {
        $this->assertSame('from-parameter', $this->configuration()->resolve('%env(MATE_TEST_WITH_DEFAULT)%'));
    }

    public function testUnsetEnvWithoutParameterThrows()
    {
        $this->expectException(FailureTransportNotReadableException::class);
        $this->expectExceptionMessage('Environment variable not found: "MATE_TEST_MISSING"');

        $this->configuration()->resolve('%env(MATE_TEST_MISSING)%');
    }

    public function testAFailureTransportReachedThroughAnAliasIsListedOnce()
    {
        file_put_contents($this->dir.'/dev/App_KernelDevDebugContainer.xml', <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <container xmlns="http://symfony.com/schema/dic/services">
                <services>
                    <service id="messenger.transport.failed" class="Symfony\Component\Messenger\Transport\TransportInterface">
                        <tag name="messenger.receiver" alias="failed" is_failure_transport="true"/>
                        <argument>doctrine://default?queue_name=failed</argument>
                        <argument type="collection"/>
                    </service>
                    <service id="messenger.failure_transports.default" alias="messenger.transport.failed"/>
                </services>
            </container>
            XML);

        $receivers = $this->configuration()->receivers();

        $this->assertCount(1, $receivers);
        $this->assertSame('failed', $receivers[0]['name']);
        $this->assertTrue($receivers[0]['failure']);
    }

    private function configuration(): ContainerConfiguration
    {
        return ContainerConfiguration::load($this->dir, new ContainerProvider(), new ProjectEnvironment($this->dir), $this->dir);
    }
}
