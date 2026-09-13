<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Invocation;

use Symfony\AI\Mate\Discovery\CapabilityRegistry;
use Symfony\AI\Mate\Exception\ResourceNotFoundException;

/**
 * Resolves a resource URI to its contents, either from a static resource or by matching
 * a registered resource template and invoking its handler with the extracted variables.
 *
 * @phpstan-type ResourceContent array{uri: string, mimeType: string|null, text?: string, blob?: string}
 * @phpstan-type ResourceReadResult array{name: string|null, description: string|null, contents: list<ResourceContent>}
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ResourceReader
{
    public function __construct(
        private CapabilityRegistry $registry,
        private HandlerInvoker $handlerInvoker,
    ) {
    }

    /**
     * @return ResourceReadResult
     */
    public function read(string $uri): array
    {
        $resource = $this->registry->findResource($uri);
        if (null !== $resource) {
            $result = $this->invoke($resource->handlerClass, $resource->handlerMethod, []);

            return [
                'name' => $resource->name,
                'description' => $resource->description,
                'contents' => $this->normalizeContents($result, $uri, $resource->mimeType),
            ];
        }

        $match = $this->registry->matchResourceTemplate($uri);
        if (null !== $match) {
            $template = $match['template'];
            $result = $this->invoke($template->handlerClass, $template->handlerMethod, $match['variables']);

            return [
                'name' => $template->name,
                'description' => $template->description,
                'contents' => $this->normalizeContents($result, $uri, $template->mimeType),
            ];
        }

        throw new ResourceNotFoundException(\sprintf('Resource "%s" not found', $uri));
    }

    /**
     * @param class-string         $handlerClass
     * @param array<string, mixed> $arguments
     */
    private function invoke(string $handlerClass, string $handlerMethod, array $arguments): mixed
    {
        return $this->handlerInvoker->call($handlerClass, $handlerMethod, $arguments);
    }

    /**
     * @return list<ResourceContent>
     */
    private function normalizeContents(mixed $result, string $uri, ?string $mimeType): array
    {
        if (\is_string($result)) {
            return [[
                'uri' => $uri,
                'mimeType' => $mimeType ?? 'text/plain',
                'text' => $result,
            ]];
        }

        if (!\is_array($result)) {
            return [[
                'uri' => $uri,
                'mimeType' => $mimeType ?? 'text/plain',
                'text' => json_encode($result, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES),
            ]];
        }

        // A single content descriptor (associative array carrying text/blob).
        if (isset($result['text']) || isset($result['blob'])) {
            return [$this->normalizeSingle($result, $uri, $mimeType)];
        }

        // A list of content descriptors.
        if (array_is_list($result) && [] !== $result) {
            $contents = [];
            foreach ($result as $item) {
                if (\is_array($item)) {
                    $contents[] = $this->normalizeSingle($item, $uri, $mimeType);
                }
            }

            if ([] !== $contents) {
                return $contents;
            }
        }

        // Any other array is serialized as JSON text.
        return [[
            'uri' => $uri,
            'mimeType' => $mimeType ?? 'text/plain',
            'text' => json_encode($result, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES),
        ]];
    }

    /**
     * @param array<string, mixed> $content
     *
     * @return ResourceContent
     */
    private function normalizeSingle(array $content, string $uri, ?string $mimeType): array
    {
        $normalized = [
            'uri' => \is_string($content['uri'] ?? null) ? $content['uri'] : $uri,
            'mimeType' => \is_string($content['mimeType'] ?? null) ? $content['mimeType'] : ($mimeType ?? 'text/plain'),
        ];

        if (isset($content['blob']) && \is_string($content['blob'])) {
            $normalized['blob'] = $content['blob'];

            return $normalized;
        }

        if (isset($content['text']) && \is_string($content['text'])) {
            $normalized['text'] = $content['text'];

            return $normalized;
        }

        $normalized['text'] = json_encode($content, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);

        return $normalized;
    }
}
