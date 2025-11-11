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

/**
 * A pre-bundled demo image with attribution metadata.
 */
final readonly class PresetImage
{
    public function __construct(
        public string $id,
        public string $label,
        public string $scenario,
        public string $file,
        public string $mimeType,
        public string $author,
        public string $sourceUrl,
        public string $license,
        public string $licenseUrl,
    ) {
    }
}
