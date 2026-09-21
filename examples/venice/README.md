# Venice Examples

Venice AI exposes a broad catalog through one OpenAI-compatible API: chat completion (with
streaming, tool calling and vision), embeddings, image generation, editing and upscaling,
text-to-speech, speech-to-text and video generation. Video is queue-based - the invocation queues
the request and returns a job handle, which a `JobRunner` resolves once the clip is ready.

```bash
php venice/chat.php
php venice/stream.php
php venice/text-to-image.php
php venice/text-to-video.php
```

All examples require `VENICE_API_KEY` to be set in `.env.local`.

`web-search.php` and `chat-with-character.php` demonstrate `venice_parameters`, Venice's own
extension of the chat completions body, through the typed `VeniceParameters` builder.

The model catalog is fetched from `GET /models` at runtime, so every model id the account can
reach works without a bridge release.

For text-to-speech, you can pipe the output to a player like [mpg123](https://www.mpg123.de/):

```bash
php venice/text-to-speech.php | mpg123 -
```
