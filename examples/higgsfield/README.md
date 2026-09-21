# Higgsfield Examples

Higgsfield generates images and videos from text prompts or reference images. Generation is
asynchronous: the invocation submits the request and returns a job handle, which a `JobRunner`
resolves by polling the status endpoint until the media is ready.

```bash
php higgsfield/text-to-image.php
php higgsfield/image-to-video.php
```

All examples require `HIGGSFIELD_API_KEY` and `HIGGSFIELD_API_SECRET` to be set in `.env.local`.
Higgsfield issues both as a single `<key-id>:<key-secret>` pair - split it at the colon.

The examples use the short aliases (`soul-2`, `kling-2.5-i2v`) that the default
`CuratedModelCatalog` provides; the full endpoint names reported by `GET /models` work just as well.
