<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Fixtures;

/**
 * @author Asrar ul haq nahvi <aszenz@gmail.com>
 */
class FrameworkDetails
{
    /**
     * @param string $extractedText the full text extracted from the document
     */
    public function __construct(
        public string $extractedText,
        public Field $latestVersion,
        public Field $programmingLanguage,
        public Field $noOfDownloads,
        public Field $noOfGithubStars,
        public Field $hasLTSVersion,
        public Field $upcomingConferences,
    ) {
    }
}
