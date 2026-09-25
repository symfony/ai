# MiniMax Examples

[MiniMax](https://platform.minimax.io) exposes several modalities through a single platform bridge:
chat (with streaming and token usage), text-to-speech (synchronous and asynchronous), image
generation, music generation and video generation.

Set `MINI_MAX_API_KEY` in `examples/.env.local` before running the examples.

## Chat

```bash
php minimax/chat.php
php minimax/chat-as-stream.php
php minimax/chat-with-token-usage.php
```

## Text-to-speech

The synchronous example returns audio as binary; pipe it to a player like [mpg123](https://www.mpg123.de/).
The asynchronous example saves the audio to `minimax/minimax-speech.mp3`:

```bash
php minimax/text-to-speech.php | mpg123 -
php minimax/text-to-speech-async.php
```

## Image, music and video

```bash
php minimax/text-to-image.php
php minimax/music.php | mpg123 -
php minimax/text-to-video.php
```
