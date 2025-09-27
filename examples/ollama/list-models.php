<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Ollama\PlatformFactory;
use Symfony\AI\Platform\Capability;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OLLAMA_HOST_URL'), http_client());
$modelDefinitions = $platform->fetchModelDefinitions();

echo "Available models:\n";
foreach ($modelDefinitions as $modelDefinition) {
    $modelCapabilities = array_map(
        static fn (Capability $capability): string => $capability->value,
        $modelDefinition->getCapabilities()
    );
    echo sprintf(
        " + %s (%s)\n",
        $modelDefinition->getName(),
        implode(', ', $modelCapabilities)
    );
}
