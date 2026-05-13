# Inworld Examples

Inworld provides text-to-speech and speech-to-text models. The bridge supports synchronous and streaming TTS.

To run the examples, you can use additional tools like [mpg123](https://www.mpg123.de/):

```bash
php inworld/text-to-speech.php | mpg123 -
php inworld/text-to-speech-as-stream.php | mpg123 -
```

Authentication uses the Base64 API key from the [Inworld Portal](https://platform.inworld.ai/). See the [Inworld API introduction](https://docs.inworld.ai/api-reference/introduction) for details.
