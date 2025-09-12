<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Tool;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
#[AsTool('sleep', 'Tool that pauses execution for a specified duration')]
final readonly class Sleep
{
    /**
     * Sleep for specified duration.
     *
     * @param int $seconds      Duration in seconds
     * @param int $milliseconds Additional milliseconds
     *
     * @return array{
     *     success: bool,
     *     duration: float,
     *     message: string,
     * }
     */
    public function __invoke(
        int $seconds = 1,
        int $milliseconds = 0,
    ): array {
        $duration = $seconds + ($milliseconds / 1000);

        if ($duration <= 0) {
            return [
                'success' => false,
                'duration' => 0.0,
                'message' => 'Duration must be greater than 0',
            ];
        }

        if ($duration > 300) { // 5 minutes max
            return [
                'success' => false,
                'duration' => $duration,
                'message' => 'Duration cannot exceed 300 seconds (5 minutes)',
            ];
        }

        usleep((int) ($duration * 1000000));

        return [
            'success' => true,
            'duration' => $duration,
            'message' => "Slept for {$duration} seconds",
        ];
    }
}
