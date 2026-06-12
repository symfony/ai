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

use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Result\DeferredResult;

/**
 * Encapsulates a single inference backend (e.g. OpenAI, Anthropic, Ollama).
 *
 * A provider holds everything needed to communicate with one AI platform:
 * model clients, result converters, contract normalization, and model catalog.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ProviderInterface
{
    /**
     * @return non-empty-string
     */
    public function getName(): string;

    /**
     * Whether this provider can handle the given model name, or fully defined model.
     *
     * @param non-empty-string|Model $model
     */
    public function supports(string|Model $model): bool;

    /**
     * @param non-empty-string|Model     $model   The model name to resolve via the catalog, or a fully defined model
     * @param array<mixed>|string|object $input   The input data
     * @param array<string, mixed>       $options The options to customize the model invocation
     */
    public function invoke(string|Model $model, array|string|object $input, array $options = []): DeferredResult;

    public function getModelCatalog(): ModelCatalogInterface;
}
