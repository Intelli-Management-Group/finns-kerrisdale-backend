# Finns Kerrisdale Ladies — Laravel API

REST API, admin authentication (Sanctum), image processing, SEO rendering and the WordPress importer.

- Setup: [../docs/INSTALLATION.md](../docs/INSTALLATION.md)
- Endpoints: [../docs/API.md](../docs/API.md)
- WordPress import: [../docs/WORDPRESS_IMPORT.md](../docs/WORDPRESS_IMPORT.md)
- Tests: `php artisan test` — see [../docs/TESTING.md](../docs/TESTING.md)

Useful commands:

| Command | Purpose |
|---|---|
| `php artisan wp:import --uploads=PATH [--dry-run]` | Import the WordPress database and images |
| `php artisan media:localize` | Copy images still linked from the old host into the media library |
| `php artisan site:check` | Report broken images, old links and missing SEO data |
| `php artisan admin:password [EMAIL]` | Reset an admin password with hidden prompts and revoke its API tokens |

To reset the admin login, run `php artisan admin:password` (or pass the admin email). The command defaults to `ADMIN_EMAIL` from `.env`; if that address is not an account, it uses the only active admin or asks you to choose when there are several. It asks for the new password twice without displaying it and requires at least 12 characters.

The admin media API provides configured folders and their item counts at `GET /api/admin/media/folders`. Image references are stored as paths, while API responses build absolute URLs from `MEDIA_BASE_URL` (falling back to `ASSET_URL` and `APP_URL`). Set `MEDIA_BASE_URL` to the public origin that the admin browser can reach when `APP_URL` is not that origin. Media files on the `public` disk are also served from `/storage/...` if the `public/storage` symlink is unavailable.
