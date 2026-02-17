# Venice Examples

These examples demonstrate the various capabilities of the Venice AI bridge.

## Available Examples

- **chat.php** - Basic chat completion with a language model
- **chat-as-stream.php** - Streaming chat completion (tokens are displayed as they arrive)
- **chat-with-vision.php** - Chat completion with an image input
- **chat-with-tools.php** - Chat completion with function calling / tools
- **chat-with-web-search.php** - Chat completion with Venice web search via `venice_parameters`
- **chat-with-character.php** - Chat completion using a Venice public character (`venice_parameters.character_slug`)
- **embeddings.php** - Generate vector embeddings from text
- **image-generation.php** - Generate an image from a text prompt
- **image-edit.php** - Edit an existing image with a prompt
- **image-upscale.php** - Upscale an image
- **text-to-speech.php** - Convert text to audio
- **speech-to-text.php** - Transcribe an audio file to text
- **video-generation.php** - Generate a video from a text
- **video-generation-from-image.php** - Generate a video from an image
- **video-to-video.php** - Transform a source video into a new video

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
php venice/speech-to-text.php
```

For text-to-speech, you can pipe the output to a player like [mpg123](https://www.mpg123.de/):

```bash
php venice/text-to-speech.php | mpg123 -
```
