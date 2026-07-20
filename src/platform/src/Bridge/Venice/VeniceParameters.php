<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Venice;

use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * Typed builder for the Venice-specific `venice_parameters` object that
 * extends the OpenAI-compatible chat completions API.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 *
 * @see https://docs.venice.ai/api-reference/endpoint/chat/completions
 */
final class VeniceParameters
{
    public const WEB_SEARCH_OFF = 'off';
    public const WEB_SEARCH_ON = 'on';
    public const WEB_SEARCH_AUTO = 'auto';

    /**
     * @param non-empty-string|null $characterSlug   Public character handle (e.g. "alan-watts")
     * @param string|null           $enableWebSearch Web search mode, one of self::WEB_SEARCH_* (validated at runtime)
     * @param array<string, mixed>  $extra           Forward unknown fields verbatim
     */
    public function __construct(
        private readonly ?string $characterSlug = null,
        private readonly ?string $enableWebSearch = null,
        private readonly ?bool $enableWebScraping = null,
        private readonly ?bool $enableXSearch = null,
        private readonly ?bool $enableWebCitations = null,
        private readonly ?bool $returnSearchResultsAsDocuments = null,
        private readonly ?bool $includeSearchResultsInStream = null,
        private readonly ?bool $includeVeniceSystemPrompt = null,
        private readonly ?bool $disableThinking = null,
        private readonly ?bool $stripThinkingResponse = null,
        private readonly ?bool $enableE2ee = null,
        private readonly array $extra = [],
    ) {
        if (null !== $enableWebSearch && !\in_array($enableWebSearch, [self::WEB_SEARCH_OFF, self::WEB_SEARCH_ON, self::WEB_SEARCH_AUTO], true)) {
            throw new InvalidArgumentException(\sprintf('"enableWebSearch" must be one of "off", "on", "auto"; got "%s".', $enableWebSearch));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = $this->extra;

        if (null !== $this->characterSlug) {
            $payload['character_slug'] = $this->characterSlug;
        }

        if (null !== $this->enableWebSearch) {
            $payload['enable_web_search'] = $this->enableWebSearch;
        }

        if (null !== $this->enableWebScraping) {
            $payload['enable_web_scraping'] = $this->enableWebScraping;
        }

        if (null !== $this->enableXSearch) {
            $payload['enable_x_search'] = $this->enableXSearch;
        }

        if (null !== $this->enableWebCitations) {
            $payload['enable_web_citations'] = $this->enableWebCitations;
        }

        if (null !== $this->returnSearchResultsAsDocuments) {
            $payload['return_search_results_as_documents'] = $this->returnSearchResultsAsDocuments;
        }

        if (null !== $this->includeSearchResultsInStream) {
            $payload['include_search_results_in_stream'] = $this->includeSearchResultsInStream;
        }

        if (null !== $this->includeVeniceSystemPrompt) {
            $payload['include_venice_system_prompt'] = $this->includeVeniceSystemPrompt;
        }

        if (null !== $this->disableThinking) {
            $payload['disable_thinking'] = $this->disableThinking;
        }

        if (null !== $this->stripThinkingResponse) {
            $payload['strip_thinking_response'] = $this->stripThinkingResponse;
        }

        if (null !== $this->enableE2ee) {
            $payload['enable_e2ee'] = $this->enableE2ee;
        }

        return $payload;
    }
}
