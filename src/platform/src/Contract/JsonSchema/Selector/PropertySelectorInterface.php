<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\JsonSchema\Selector;

use Symfony\AI\Platform\Contract\JsonSchema\Subject\ObjectSubject;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\PropertySubject;

/**
 * Decides which properties of an object are open for generation, i.e. described in the JSON schema.
 *
 * Passed to the describers as the `Factory::CONTEXT_SELECTOR` context and applied by the `Describer`
 * before a property is described, so the describers themselves stay a function of the class.
 *
 * @author Marco van Angeren <marco@jouwweb.nl>
 */
interface PropertySelectorInterface
{
    /**
     * Whether the model may generate this property of the object being described.
     */
    public function isOpen(ObjectSubject $object, PropertySubject $property): bool;

    /**
     * The selector applied to the object held by this property, or null to describe it in full.
     *
     * Returning a selector also marks the property as populated in place: its schema is dropped when the
     * selector opens nothing, and can never be answered with `null`.
     */
    public function forProperty(PropertySubject $property): ?self;
}
