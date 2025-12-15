<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Document;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface SourceLoaderInterface
{
    /**
     * @param string|array<mixed> $source
     *
     * @return iterable<SourceInterface>
     */
    public static function createSource(string|array $source): iterable;

    /**
     * @return class-string<SourceInterface>
     */
    public static function supportedSource(): string;

    /**
     * @param SourceInterface      $source  Source descriptor instance to load the documents from
     * @param array<string, mixed> $options loader specific set of options to control the loading process
     *
     * @return iterable<EmbeddableDocumentInterface> iterable of embeddable documents loaded from the source
     */
    public function load(SourceInterface $source, array $options = []): iterable;
}
