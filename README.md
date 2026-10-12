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
