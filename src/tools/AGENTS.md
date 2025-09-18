# AGENTS.md

## AI Agent Tools Package

This package provides third-party tools for AI agents including:

- **Brave Search**: Web search functionality using the Brave Search API
- **Firecrawl**: Website scraping, crawling, and URL mapping capabilities
- **Mapbox**: Geocoding and reverse geocoding services
- **OpenMeteo**: Weather data and forecasting services
- **SerpApi**: Internet search functionality via SerpApi
- **Tavily**: Web search and content extraction services
- **Wikipedia**: Search and article retrieval from Wikipedia
- **YouTube Transcriber**: Fetch transcripts from YouTube videos

### Usage

These tools can be registered with AI agents to provide external data access capabilities. Each tool is automatically configured with the `#[AsTool]` attribute for easy integration.

### Requirements

- PHP 8.2+
- Various API keys depending on tools used:
  - Brave Search API key (for Brave tool)
  - Mapbox access token (for Mapbox tool)
  - SerpApi key (for SerpApi tool)
  - Tavily API key (for Tavily tool)
- HTTP client implementation
- Optional: `mrmysql/youtube-transcript` package (for YouTube Transcriber)