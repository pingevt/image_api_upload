# Image API Upload

A flexible Drupal module for uploading images via a RESTful API endpoint.
Supports configurable media bundle, image field, credit, caption, and taxonomy/tag fields.
Handles file storage, media entity creation, and dynamic taxonomy term creation.

---

## Features

- Upload images to Drupal via HTTP POST (e.g., with `curl`)
- Configurable media bundle and fields (image, credit, caption, tags)
- Optional upload directory (validated and transliterated)
- Automatically creates taxonomy terms if they do not exist
- Stores settings in Drupal state (runtime, not config)
- Returns JSON response with media entity ID and upload directory

---

## Installation

1. Place this module in `web/modules/custom/image_api_upload`
2. Enable the module: `drush en image_api_upload`
3. Configure the module at `/admin/config/media/image-api-upload`

---

## Configuration

Go to Configuration → Media → Image API Upload (`/admin/config/media/image-api-upload`) and select:

- Media bundle (e.g., "image")
- Image field (e.g., field_media_image)
- Credit field (e.g., field_credit)
- Caption field (optional, e.g., field_caption)
- Media Tags field (optional, taxonomy reference field)
- All settings are stored in Drupal state and can be changed at runtime.

---

## API Usage

Endpoint

```sh
POST /image-api-upload/upload
```

### Parameters

| Name       | Type   | Required | Description                                |
| ---------- | ------ | -------- | ------------------------------------------ |
| image      | file   | Yes      | The image file to upload                   |
| name       | string | No       | Media entity name/title                    |
| alt        | string | No       | Alt text for the image                     |
| credit     | string | No       | Credit text for the image                  |
| caption    | string | No       | Caption text for the image (if configured) |
| upload_dir | string | No       | Optional subdirectory for file storage     |
| media_tags | string | No       | Comma-separated list of taxonomy           |

---

Example: Upload an Image with Tags and Caption

```sh
curl -X POST http://your-drupal-site/image-api-upload/upload \
  -F 'image=@/path/to/image.jpg' \
  -F 'name=Sample Image' \
  -F 'alt=Alt text here' \
  -F 'credit=Photographer Name' \
  -F 'caption=This is a caption for the image.' \
  -F 'upload_dir=custom_uploads' \
  -F 'media_tags=Nature, UVA, Campus'
```

Response:

```sh
{
  "message": "Upload successful.",
  "media_id": 123,
  "directory": "public://custom_uploads"
}
```

---

## Notes

The upload_dir is sanitized and transliterated for safety.

If media_tags are provided, terms are created in the configured vocabulary if they do not exist.

The API returns a JSON response with the new media entity ID and the directory used.

All settings are runtime (Drupal state), not config—no config export/import required.

---

## Troubleshooting
Ensure the media bundle and fields are configured before uploading.
The user making the API call must have permission to create media entities and upload files.
Check Drupal logs for errors if uploads fail.

---

## License

This project is licensed under the GNU General Public License, version 2 or later.
See LICENSE.txt for details.

---

## Maintainers

<!-- University of Virginia Web Team -->
<!-- For questions or contributions, please open an issue or pull request. -->
