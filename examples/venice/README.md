# Venice Examples

These examples demonstrate the various capabilities of the Venice AI bridge.

## Available Examples

- **chat.php** - Basic chat completion with a language model
- **chat-as-stream.php** - Streaming chat completion (tokens are displayed as they arrive)
- **embeddings.php** - Generate vector embeddings from text
- **image-generation.php** - Generate an image from a text prompt
- **text-to-speech.php** - Convert text to audio
- **transcription.php** - Transcribe an audio file to text
- **video-generation.php** - Generate a video from a text
- **video-generation-from-image.php** - Generate a video from an image

## Setup

Set your Venice API key in `.env.local`:

```
VENICE_API_KEY=your-api-key
```

## Running

```bash
php venice/chat.php
php venice/chat-as-stream.php
php venice/embeddings.php
php venice/image-generation.php
php venice/transcription.php
```

For text-to-speech, you can pipe the output to a player like [mpg123](https://www.mpg123.de/):

```bash
php venice/text-to-speech.php | mpg123 -
```
