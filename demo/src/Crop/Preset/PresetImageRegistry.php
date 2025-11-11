<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Crop\Preset;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Curated demo images for the smart-cropping showcase.
 *
 * Every entry has a strongly off-centre composition so the smart crop visibly
 * differs from a naive centre crop, and carries the attribution required by
 * its licence.
 */
final readonly class PresetImageRegistry
{
    /**
     * @var array<string, PresetImage>
     */
    private array $presets;

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/images/samples')]
        private string $directory,
    ) {
        $entries = [
            new PresetImage(
                id: 'accordion',
                label: 'Accordion player',
                scenario: 'Subject in the left third, dark doorway dominates the right.',
                file: 'accordion.jpg',
                mimeType: 'image/jpeg',
                author: 'Jefferson Lucena',
                sourceUrl: 'https://www.pexels.com/photo/man-playing-accordion-10153219/',
                license: 'Pexels License',
                licenseUrl: 'https://www.pexels.com/license/',
            ),
            new PresetImage(
                id: 'cyclist-road',
                label: 'Cyclist on a country road',
                scenario: 'Tiny cyclist on the left, road and sky fill the frame.',
                file: 'cyclist-road.jpg',
                mimeType: 'image/jpeg',
                author: 'Lisa from Pexels',
                sourceUrl: 'https://www.pexels.com/photo/lonely-cyclist-on-a-quiet-country-road-28828417/',
                license: 'Pexels License',
                licenseUrl: 'https://www.pexels.com/license/',
            ),
            new PresetImage(
                id: 'mountain-hiker',
                label: 'Hiker on a ridge',
                scenario: 'Hiker silhouetted near the top - extreme negative space below and around.',
                file: 'mountain-hiker.jpg',
                mimeType: 'image/jpeg',
                author: 'Piotr Baranowski',
                sourceUrl: 'https://www.pexels.com/photo/lone-trekker-on-dramatic-mountain-ridge-31091291/',
                license: 'Pexels License',
                licenseUrl: 'https://www.pexels.com/license/',
            ),
            new PresetImage(
                id: 'cat-window',
                label: 'Cat at a window',
                scenario: 'Cat on the left looking right - asymmetric horizontal composition.',
                file: 'cat-window.jpg',
                mimeType: 'image/jpeg',
                author: 'Willian Santos',
                sourceUrl: 'https://www.pexels.com/photo/curious-tabby-cat-gazing-out-a-window-32552048/',
                license: 'Pexels License',
                licenseUrl: 'https://www.pexels.com/license/',
            ),
            new PresetImage(
                id: 'four-dogs',
                label: 'Four dogs at golden hour',
                scenario: 'Multiple subjects clustered in the lower-left third - tests confidence-filtered union.',
                file: 'four-dogs.jpg',
                mimeType: 'image/jpeg',
                author: 'Basile Morin',
                sourceUrl: 'https://commons.wikimedia.org/wiki/File:Four_dogs_running_at_golden_hour_in_the_countryside_of_Don_Det_Laos.jpg',
                license: 'CC BY-SA 4.0',
                licenseUrl: 'https://creativecommons.org/licenses/by-sa/4.0/',
            ),
        ];

        $byId = [];
        foreach ($entries as $entry) {
            $byId[$entry->id] = $entry;
        }
        $this->presets = $byId;
    }

    /**
     * @return array<string, PresetImage>
     */
    public function all(): array
    {
        return $this->presets;
    }

    public function get(string $id): PresetImage
    {
        if (!isset($this->presets[$id])) {
            throw new \InvalidArgumentException(\sprintf('Unknown preset image "%s".', $id));
        }

        return $this->presets[$id];
    }

    public function loadAsDataUrl(string $id): string
    {
        $preset = $this->get($id);
        $path = $this->directory.'/'.$preset->file;
        $bytes = @file_get_contents($path);

        if (false === $bytes) {
            throw new \RuntimeException(\sprintf('Failed to read preset image "%s" from "%s".', $id, $path));
        }

        return 'data:'.$preset->mimeType.';base64,'.base64_encode($bytes);
    }
}
