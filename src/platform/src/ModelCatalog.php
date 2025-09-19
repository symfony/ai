<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
class ModelCatalog extends AbstractModelCatalog
{
    public function supports(PlatformInterface $platform): bool
    {
        return [] !== $this->getSupportedModels($platform);
    }

    /**
     * @return list<string>
     */
    public function getSupportedModels(PlatformInterface $platform): array
    {
        $supportedModels = [];

        foreach ($this->models as $modelName => $modelConfig) {
            if ($this->isModelSupportedByPlatform($modelName, $platform)) {
                $supportedModels[] = $modelName;
            }
        }

        return $supportedModels;
    }

    private function isModelSupportedByPlatform(string $modelName, PlatformInterface $platform): bool
    {
        try {
            $model = $this->getModel($modelName);
            $platform->invoke($model, '');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
