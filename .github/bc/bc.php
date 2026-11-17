<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * Checks changes against the Symfony Backward Compatibility Promise.
 *
 * usage: php .github/bc/bc.php dump                 Write the API snapshot of every package to .github/bc/api/
 *        php .github/bc/bc.php check                Fail when an API snapshot is outdated
 *        php .github/bc/bc.php compare <base-ref>   Report the BC impact of the changes since <base-ref>
 *
 * "compare" reads PR_LABELS (JSON array) and emits GitHub Actions annotations when GITHUB_ACTIONS is set.
 */

use Symfony\AI\Bc\ApiExtractor;
use Symfony\AI\Bc\Command;

require dirname(__DIR__, 2).'/vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Symfony\\AI\\Bc\\')) {
        require __DIR__.'/src/'.str_replace('\\', '/', substr($class, strlen('Symfony\\AI\\Bc\\'))).'.php';
    }
});

exit((new Command(dirname(__DIR__, 2), new ApiExtractor()))->run(array_slice($argv, 1)));
