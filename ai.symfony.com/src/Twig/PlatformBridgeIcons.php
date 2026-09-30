<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Twig;

use App\PlatformBridge\PlatformBridgeCatalog;
use Symfony\UX\Icons\IconRendererInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * Renders the icons of the platform bridge cards as an SVG sprite: each icon is
 * defined once in the page and referenced by the cards, which keeps the HTML sent
 * on every Live Component re-render small.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final readonly class PlatformBridgeIcons
{
    /**
     * Icons of the cards, besides the logos.
     */
    private const array ICONS = [
        'tabler:arrow-up-right',
        'tabler:check',
        'tabler:copy',
        'tabler:info-circle',
    ];

    public function __construct(
        private IconRendererInterface $iconRenderer,
        private PlatformBridgeCatalog $catalog,
    ) {
    }

    #[AsTwigFunction('platform_bridge_icon_sprite', isSafe: ['html'])]
    public function renderSprite(): string
    {
        $names = self::ICONS;

        foreach ($this->catalog->getBridges() as $bridge) {
            if (null !== $bridge->icon) {
                $names[] = $bridge->icon;
            }
        }

        $symbols = '';
        foreach (array_unique($names) as $name) {
            $svg = $this->iconRenderer->renderIcon($name);
            $symbols .= (string) preg_replace(['/^<svg\b/', '/<\/svg>$/'], ['<symbol id="'.self::id($name).'"', '</symbol>'], trim($svg));
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" class="d-none" aria-hidden="true">'.$symbols.'</svg>';
    }

    /**
     * @param array<string, string|int> $attributes
     */
    #[AsTwigFunction('platform_bridge_icon', isSafe: ['html'])]
    public function renderIcon(string $name, array $attributes = []): string
    {
        $html = '';
        foreach (['aria-hidden' => 'true', ...$attributes] as $attribute => $value) {
            $html .= \sprintf(' %s="%s"', $attribute, htmlspecialchars((string) $value, \ENT_QUOTES | \ENT_SUBSTITUTE));
        }

        return \sprintf('<svg%s><use href="#%s"></use></svg>', $html, self::id($name));
    }

    private static function id(string $name): string
    {
        return 'pb-icon-'.str_replace(':', '-', $name);
    }
}
