<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi;

/**
 * Derives the Vertex AI API host from the configured location so that regional
 * and data-residency (jurisdictional) endpoints are used when required.
 *
 * @see https://docs.cloud.google.com/vertex-ai/generative-ai/docs/learn/locations
 * @see https://docs.cloud.google.com/vertex-ai/generative-ai/docs/learn/data-residency
 *
 * @author Robbe Serry <robbeserry@gmail.com>
 */
trait RegionAwareTrait
{
    /**
     * Multi-region data-residency (jurisdictional) endpoints keyed by location.
     */
    private const RESIDENCY_HOSTS = [
        'eu' => 'aiplatform.eu.rep.googleapis.com',
        'us' => 'aiplatform.us.rep.googleapis.com',
    ];

    private function resolveHost(?string $location): string
    {
        if (null === $location || 'global' === $location) {
            return 'aiplatform.googleapis.com';
        }

        if (isset(self::RESIDENCY_HOSTS[$location])) {
            return self::RESIDENCY_HOSTS[$location];
        }

        return \sprintf('%s-aiplatform.googleapis.com', $location);
    }
}
