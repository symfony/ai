# Deepgram Examples

This directory shows how to call Deepgram both for text-to-speech (TTS) and speech-to-text (STT),
over HTTP and WebSocket.

To play back the audio produced by the TTS examples, pipe the output into a player such as
[mpg123](https://www.mpg123.de/):

```bash
php deepgram/text-to-speech.php | mpg123 -
php deepgram/text-to-speech-as-websocket.php | mpg123 -
```

The STT example writes the transcribed text directly to stdout:

```bash
php deepgram/speech-to-text.php
```

Set `DEEPGRAM_API_KEY` in `.env.local` before running the examples.
