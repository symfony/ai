<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\ToolFactory;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Exception\ToolException;

/**
 * Metadata factory that uses reflection in combination with `#[AsTool]` attribute to extract metadata from tools.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ReflectionToolFactory extends AbstractToolFactory
{
    /**
     * @param class-string $reference
     */
    public function getTool(string $reference): iterable
    {
        if (!class_exists($reference)) {
            throw ToolException::invalidReference($reference);
        }

        $reflectionClass = new \ReflectionClass($reference);
        $attributes = $reflectionClass->getAttributes(AsTool::class);

        if (0 === \count($attributes)) {
            throw ToolException::missingAttribute($reference);
        }

        foreach ($attributes as $attribute) {
            yield $this->convertAttribute($reference, $attribute->newInstance());
        }
    }
}
