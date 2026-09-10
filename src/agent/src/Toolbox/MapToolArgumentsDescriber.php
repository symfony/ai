<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox;

use Symfony\AI\Platform\Contract\JsonSchema\Describer\Describer;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\ObjectDescriberInterface;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\ObjectSubject;

/**
 * @author Illia Vasylevskyi <ineersa@gmail.com>
 */
final class MapToolArgumentsDescriber implements ObjectDescriberInterface
{
    public function __construct(
        private readonly ObjectDescriberInterface $inner = new Describer(),
    ) {
    }

    public function describeObject(ObjectSubject $subject, ?array &$schema): iterable
    {
        $reflector = $subject->getReflector();
        if ($reflector instanceof \ReflectionMethod && null !== $mapped = MappedToolArgument::forMethod($reflector)) {
            $subject = new ObjectSubject($mapped->className, new \ReflectionClass($mapped->className), $subject->getContext());
        }

        return $this->inner->describeObject($subject, $schema);
    }
}
