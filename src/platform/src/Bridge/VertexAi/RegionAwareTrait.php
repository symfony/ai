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

use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * Builds the Vertex AI endpoint URL, deriving the API host from the configured location so that
 * regional and data-residency (jurisdictional) endpoints are used when required.
 *
 * @see https://docs.cloud.google.com/vertex-ai/generative-ai/docs/learn/locations
 * @see https://docs.cloud.google.com/vertex-ai/generative-ai/docs/learn/data-residency
 *
 * @author Robbe Serry <robbeserry@gmail.com>
 */
trait RegionAwareTrait
{
    private const GLOBAL_HOST = 'aiplatform.googleapis.com';

    /**
     * Multi-region data-residency (jurisdictional) endpoints keyed by location.
     */
    private const RESIDENCY_HOSTS = [
        'eu' => 'aiplatform.eu.rep.googleapis.com',
        'us' => 'aiplatform.us.rep.googleapis.com',
    ];

    /**
     * Locations are lowercase, e.g. "global", "eu", "us", "europe-west1" or "northamerica-northeast1".
     */
    private const LOCATION_PATTERN = '/^[a-z]+(-[a-z]+\d+)?$/';

    /**
     * The location is only part of the URL for the project-scoped endpoint, so without a project ID
     * the global endpoint is the only one that can be addressed.
     */
    private static function getEndpoint(?string $location, ?string $projectId, string $model, string $method): string
    {
        if (null === $location || null === $projectId) {
            return \sprintf('https://%s/v1/publishers/google/models/%s:%s', self::GLOBAL_HOST, $model, $method);
        }

        $location = strtolower($location);

        return \sprintf(
            'https://%s/v1/projects/%s/locations/%s/publishers/google/models/%s:%s',
            self::getHost($location),
            $projectId,
            $location,
            $model,
            $method,
        );
    }

    private static function getHost(string $location): string
    {
        if (1 !== preg_match(self::LOCATION_PATTERN, $location)) {
            throw new InvalidArgumentException(\sprintf('Invalid location "%s". Valid options are "global", "eu", "us", or a region like "europe-west1".', $location));
        }

        if ('global' === $location) {
            return self::GLOBAL_HOST;
        }

        return self::RESIDENCY_HOSTS[$location] ?? \sprintf('%s-aiplatform.googleapis.com', $location);
    }
}
