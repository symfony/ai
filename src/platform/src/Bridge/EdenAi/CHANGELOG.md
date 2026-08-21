CHANGELOG
=========

0.13
----

 * Add the bridge
 * Add OCR and document parsing (financial, resume, identity) support via the `/v3/universal-ai` endpoint
 * Add text-to-speech, speech-to-text, image analysis (object detection, explicit content, logo detection, face detection, AI detection, deepfake detection) and image generation support
 * Add transparent binary file upload via the `/v3/upload` endpoint for `Audio`, `Document` and `Image` content
 * Add `ModelApiCatalog`, discovering every served model from the `/v3/models`, `/v3/embeddings/models` and `/v3/info` endpoints instead of the curated `ModelCatalog` subset
 * Report the synthesized audio format from the resource URL, since the CDN serving it answers `binary/octet-stream` whatever the requested `audio_format` was
