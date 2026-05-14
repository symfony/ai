<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\TransformersPhp;

use Symfony\AI\Platform\Endpoint;
use Symfony\AI\Platform\ModelCatalog\FallbackModelCatalog;

/**
 * TransformersPhp can use various models from HuggingFace, dynamically
 * loaded through the transformers.php library — so the catalog accepts
 * any model name and points each one at the in-process pipeline contract.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalog extends FallbackModelCatalog
{
    protected function endpointsForModel(array $modelConfig): array
    {
        return [new Endpoint(PipelineClient::ENDPOINT)];
    }
}
