<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Twig;

use App\PlatformBridge\PlatformBridgeCatalog;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

final class PlatformBridgeIconsTest extends KernelTestCase
{
    public function testTheSpriteDefinesTheIconOfEveryBridge()
    {
        $sprite = $this->renderSprite();

        foreach (static::getContainer()->get(PlatformBridgeCatalog::class)->getBridges() as $bridge) {
            if (null !== $bridge->icon) {
                $this->assertStringContainsString(\sprintf('<symbol id="pb-icon-%s"', str_replace(':', '-', $bridge->icon)), $sprite);
            }
        }
    }

    public function testLogosSharingTheSpriteCannotCollide()
    {
        $sprite = $this->renderSprite();

        preg_match_all('/\\sid="([^"]+)"/', $sprite, $matches);

        $this->assertSame([], array_values(array_unique(array_diff_assoc($matches[1], array_unique($matches[1])))), 'Prefix the ids of the logos with their name.');
        $this->assertStringNotContainsString('<style', $sprite, 'Styles of a logo would apply to the whole page.');
    }

    public function testTheSpriteIsNotRemovedFromTheRendering()
    {
        // gradients of a sprite hidden with display: none are not painted
        $this->assertStringNotContainsString('d-none', $this->renderSprite());
    }

    private function renderSprite(): string
    {
        return static::getContainer()->get(Environment::class)->createTemplate('{{ platform_bridge_icon_sprite() }}')->render();
    }
}
