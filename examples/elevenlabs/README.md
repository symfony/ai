# Elevenlabs Examples

One use case of Elevenlabs is to convert text to speech, which creates audio files from text input.

The text-to-speech examples use Wyatt - Seasoned Mentor. To use this Voice
Library voice through the API, use a paid ElevenLabs plan and add Wyatt to My
Voices if it is missing. You can also set the `voice` option to a voice ID
available to your account. See the [ElevenLabs Voice Library guide](https://elevenlabs.io/docs/eleven-creative/voices/voice-library).

To run the examples, you can use additional tools like (mpg123)[https://www.mpg123.de/]:

```bash
php elevenlabs/text-to-speech.php | mpg123 -
```
