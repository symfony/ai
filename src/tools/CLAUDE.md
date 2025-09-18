# CLAUDE.md

This package provides third-party tools for Symfony AI agents.

## Available Tools

### Brave Search Tool
- Provides web search functionality via Brave Search API
- Requires API key configuration
- Returns formatted search results

### Firecrawl Tool
- Website scraping with `firecrawl_scrape` method
- Website crawling with `firecrawl_crawl` method
- URL mapping with `firecrawl_map` method
- Requires API key and endpoint configuration

### Mapbox Tool
- Address geocoding with `geocode` method
- Reverse geocoding with `reverse_geocode` method
- Requires Mapbox access token

### OpenMeteo Tool
- Current weather data with `weather_current` method
- Weather forecasting with `weather_forecast` method
- No API key required (public API)

### SerpApi Tool
- Internet search functionality via SerpApi
- Requires SerpApi key configuration
- Returns search result titles

### Tavily Tool
- Web search with `tavily_search` method
- Content extraction with `tavily_extract` method
- Requires Tavily API key

### Wikipedia Tool  
- Searches Wikipedia articles with `wikipedia_search` method
- Retrieves full article content with `wikipedia_article` method
- Supports multiple languages
- No API key required

### YouTube Transcriber Tool
- Fetches transcripts from YouTube videos
- Requires `mrmysql/youtube-transcript` package
- No API key required

## Development

Run tests:
```bash
vendor/bin/phpunit
```

Run static analysis:
```bash
vendor/bin/phpstan analyse
```

## Tool Integration

All tools are automatically configured with the `#[AsTool]` attribute and can be easily integrated into AI agents using the agent framework.