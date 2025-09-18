# Symfony AI Toolbox

A collection of third-party tools for Symfony AI agents.

## Available Tools

- [**Brave Search**](#brave-search-tool): Web search functionality using the Brave Search API
- [**Firecrawl**](#firecrawl-tool): Website scraping, crawling, and URL mapping capabilities
- [**Mapbox**](#mapbox-tool): Geocoding and reverse geocoding services
- [**OpenMeteo**](#openmeteo-tool): Weather data and forecasting services
- [**SerpApi**](#serpapi-tool): Internet search functionality via SerpApi
- [**Tavily**](#tavily-tool): Web search and content extraction services
- [**Wikipedia**](#wikipedia-tool): Search and retrieve Wikipedia articles
- [**YouTube Transcriber**](#youtube-transcriber-tool): Fetch transcripts from YouTube videos

## Installation

```bash
composer require symfony/ai-tools
```

## Usage

### Brave Search Tool

```php
use Symfony\AI\Toolbox\Tool\Brave;
use Symfony\Component\HttpClient\HttpClient;

$httpClient = HttpClient::create();
$brave = new Brave($httpClient, 'your-brave-api-key');

$results = $brave('search query');
```

### Firecrawl Tool

```php
use Symfony\AI\Toolbox\Tool\Firecrawl;
use Symfony\Component\HttpClient\HttpClient;

$httpClient = HttpClient::create();
$firecrawl = new Firecrawl($httpClient, 'your-api-key', 'https://api.firecrawl.dev');

// Scrape a single page
$scraped = $firecrawl->scrape('https://example.com');

// Crawl an entire website
$crawled = $firecrawl->crawl('https://example.com');

// Map all URLs from a website
$mapped = $firecrawl->map('https://example.com');
```

### Mapbox Tool

```php
use Symfony\AI\Toolbox\Tool\Mapbox;
use Symfony\Component\HttpClient\HttpClient;

$httpClient = HttpClient::create();
$mapbox = new Mapbox($httpClient, 'your-mapbox-access-token');

// Convert address to coordinates
$coordinates = $mapbox->geocode('1600 Pennsylvania Ave, Washington DC');

// Convert coordinates to address
$address = $mapbox->reverseGeocode(-77.0365, 38.8977);
```

### OpenMeteo Tool

```php
use Symfony\AI\Toolbox\Tool\OpenMeteo;
use Symfony\Component\HttpClient\HttpClient;

$httpClient = HttpClient::create();
$openMeteo = new OpenMeteo($httpClient);

// Get current weather
$currentWeather = $openMeteo->current(52.52, 13.4050); // Berlin coordinates

// Get weather forecast
$forecast = $openMeteo->forecast(52.52, 13.4050); // Berlin coordinates
```

### SerpApi Tool

```php
use Symfony\AI\Toolbox\Tool\SerpApi;
use Symfony\Component\HttpClient\HttpClient;

$httpClient = HttpClient::create();
$serpApi = new SerpApi($httpClient, 'your-serpapi-key');

$results = $serpApi('artificial intelligence trends');
```

### Tavily Tool

```php
use Symfony\AI\Toolbox\Tool\Tavily;
use Symfony\Component\HttpClient\HttpClient;

$httpClient = HttpClient::create();
$tavily = new Tavily($httpClient, 'your-tavily-api-key');

// Search for information
$searchResults = $tavily->search('latest AI developments');

// Extract content from a website
$extractedContent = $tavily->extract(['https://example.com']);
```

### Wikipedia Tool

```php
use Symfony\AI\Toolbox\Tool\Wikipedia;
use Symfony\Component\HttpClient\HttpClient;

$httpClient = HttpClient::create();
$wikipedia = new Wikipedia($httpClient);

// Search for articles
$searchResults = $wikipedia->search('artificial intelligence');

// Get article content
$article = $wikipedia->article('Artificial intelligence');

// Use different language (optional)
$germanWikipedia = new Wikipedia($httpClient, 'de');
```

### YouTube Transcriber Tool

```php
use Symfony\AI\Toolbox\Tool\YouTubeTranscriber;
use Symfony\Component\HttpClient\HttpClient;

$httpClient = HttpClient::create();
$transcriber = new YouTubeTranscriber($httpClient);

// Get transcript from YouTube video ID
$transcript = $transcriber('dQw4w9WgXcQ');
```

## Requirements

- PHP 8.2 or higher
- Symfony HTTP Client
- API keys for specific tools:
  - Brave Search API key (for Brave tool)
  - Firecrawl API key and endpoint (for Firecrawl tool)
  - Mapbox access token (for Mapbox tool)
  - SerpApi key (for SerpApi tool)
  - Tavily API key (for Tavily tool)
- Optional dependencies:
  - `mrmysql/youtube-transcript` package (for YouTube Transcriber tool)

**Note**: OpenMeteo and Wikipedia tools require no API keys.

## Testing

```bash
vendor/bin/phpunit
```

## Static Analysis

```bash
vendor/bin/phpstan analyse
```