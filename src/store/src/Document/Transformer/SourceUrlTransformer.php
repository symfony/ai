<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Document\Transformer;

use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\TransformerInterface;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\Component\Filesystem\Path;

/**
 * @author Christopher X. Candreva <chris@westnet.com>
 */
final class SourceUrlTransformer implements TransformerInterface
{
    private readonly string $pathPrefix;
    private readonly string $urlPrefix;

    public function __construct(string $pathPrefix, string $urlPrefix)
    {
        $pathPrefix = Path::canonicalize($pathPrefix);
        if (!Path::isLocal($pathPrefix) || !Path::isAbsolute($pathPrefix)) {
            throw new InvalidArgumentException('The path prefix must be an absolute local directory path.');
        }

        $url = parse_url($urlPrefix);
        if (false === filter_var($urlPrefix, \FILTER_VALIDATE_URL) || !\is_array($url) || !\in_array(strtolower($url['scheme'] ?? ''), ['http', 'https'], true) || isset($url['query']) || isset($url['fragment'])) {
            throw new InvalidArgumentException('The URL prefix must be an absolute HTTP or HTTPS URL without a query string or fragment.');
        }

        $this->pathPrefix = rtrim(Path::canonicalize($pathPrefix), '/').'/';
        $this->urlPrefix = rtrim($urlPrefix, '/').'/';
    }

    public function transform(iterable $documents, array $options = []): iterable
    {
        foreach ($documents as $document) {
            $metadata = $document->getMetadata();
            $source = $metadata[Metadata::KEY_SOURCE] ?? null;
            if ($metadata->offsetExists('source_url') || !\is_string($source) || !Path::isLocal($source)) {
                yield $document;

                continue;
            }

            $path = Path::makeAbsolute(Path::canonicalize($source), getcwd());
            if (!str_starts_with($path, $this->pathPrefix) || '' === $relativePath = substr($path, \strlen($this->pathPrefix))) {
                yield $document;

                continue;
            }

            $metadata = new Metadata($metadata->getArrayCopy());
            $metadata['source_url'] = $this->urlPrefix.implode('/', array_map('rawurlencode', explode('/', $relativePath)));

            yield new TextDocument($document->getId(), $document->getContent(), $metadata);
        }
    }
}
