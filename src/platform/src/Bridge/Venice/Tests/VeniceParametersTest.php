<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Venice\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Venice\VeniceParameters;
use Symfony\AI\Platform\Exception\InvalidArgumentException;

final class VeniceParametersTest extends TestCase
{
    public function testEmptyParametersReturnEmptyArray()
    {
        $this->assertSame([], (new VeniceParameters())->toArray());
    }

    public function testAllFieldsAreSerialized()
    {
        $parameters = new VeniceParameters(
            characterSlug: 'alan-watts',
            enableWebSearch: VeniceParameters::WEB_SEARCH_AUTO,
            enableWebScraping: true,
            enableXSearch: false,
            enableWebCitations: true,
            returnSearchResultsAsDocuments: false,
            includeSearchResultsInStream: true,
            includeVeniceSystemPrompt: false,
            disableThinking: true,
            stripThinkingResponse: false,
            enableE2ee: true,
        );

        $this->assertSame([
            'character_slug' => 'alan-watts',
            'enable_web_search' => 'auto',
            'enable_web_scraping' => true,
            'enable_x_search' => false,
            'enable_web_citations' => true,
            'return_search_results_as_documents' => false,
            'include_search_results_in_stream' => true,
            'include_venice_system_prompt' => false,
            'disable_thinking' => true,
            'strip_thinking_response' => false,
            'enable_e2ee' => true,
        ], $parameters->toArray());
    }

    public function testExtraFieldsAreForwardedVerbatim()
    {
        $parameters = new VeniceParameters(extra: ['custom_flag' => 'on']);

        $this->assertSame(['custom_flag' => 'on'], $parameters->toArray());
    }

    public function testInvalidWebSearchModeThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"enableWebSearch" must be one of "off", "on", "auto"; got "yes".');
        new VeniceParameters(enableWebSearch: 'yes');
    }

    public function testWebSearchAcceptsAllAllowedValues()
    {
        $this->assertSame(['enable_web_search' => 'off'], (new VeniceParameters(enableWebSearch: VeniceParameters::WEB_SEARCH_OFF))->toArray());
        $this->assertSame(['enable_web_search' => 'on'], (new VeniceParameters(enableWebSearch: VeniceParameters::WEB_SEARCH_ON))->toArray());
        $this->assertSame(['enable_web_search' => 'auto'], (new VeniceParameters(enableWebSearch: VeniceParameters::WEB_SEARCH_AUTO))->toArray());
    }
}
