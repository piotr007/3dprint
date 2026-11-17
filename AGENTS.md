# 3dprint.4fm.pl — AI Agent Guide

Portfolio site for 3D-printing projects: public gallery + Filament admin panel (CMS).
Full project plan and decisions (in Polish): `plan.md`. Operational command log: `commands-log.md`.
In-depth handover docs (PL; architecture, environment, domain, media pipeline, Filament 5 notes,
troubleshooting, photo-portfolio blueprint, full code snapshot): `docs/ai-handover/README.md`.

## Tech stack

| Component | Version | Notes |
|---|---|---|
| PHP | 8.3 | runs in Docker container `app` |
| Laravel | 13.x | monolith, no separate API |
| Filament | 5.x | admin panel at `/admin` — **see "Filament 5 gotchas" below** |
| MySQL | 8.4 | Docker container `db` |
| Vite + Tailwind | — | requires Node 18+ (Node 12 fails) |
| Frontend | Blade | public pages, English UI |

## Dev environment (Docker)

Four containers via `docker-compose.yml`: `app` (PHP-FPM), `web` (nginx, port `${WEB_PORT:-8080}`), `db` (MySQL, port 3306), `mailpit` (mail catcher; SMTP `mailpit:1025` inside the network, web UI on `${MAILPIT_PORT:-8025}`).

```sh
docker compose up -d                              # start
docker compose exec app php artisan migrate       # migrations
docker compose exec app php artisan db:seed       # idempotent seed (updateOrCreate)
docker compose exec app php artisan tinker        # REPL for debugging
docker compose exec app npm run build             # Vite build (rebuild app image for Node 22)
./deploy-package.sh                              # production FTP ZIP; Docker-only build tools
./scripts/db.sh backup                            # DB dump -> backups/mysql/
./scripts/db.sh restore <file.sql>                # DB restore
./zip-project.sh                                  # zip project to parent dir (excludes vendor/storage/node_modules)
```

> **Permissions gotcha:** `docker compose exec app ...` runs as **root**. Any artisan command that compiles Blade views or writes to `storage/` (tests, tinker rendering views) leaves root-owned files that PHP-FPM (www-data) later can't `touch()` → `ErrorException: Utime failed`. Prefer `docker compose exec -u www-data app php artisan ...`; if it already happened, fix with `docker compose exec app sh -c 'php artisan view:clear && chown -R www-data:www-data storage bootstrap/cache'`.

- Local URL: `http://3dprint.local:8080` (hosts entry) or `http://localhost:8080`.
- Admin: `http://3dprint.local:8080/admin`, seeded login `admin@3dprint.local` / `password`.
- `.env` essentials: `APP_URL=http://3dprint.local:8080`, `APP_KEY` must be set, `TMPDIR=/var/www/html/storage/tmp` (uploads fail without it; dir created in `docker/php/Dockerfile`), `MAIL_MAILER=smtp` + `MAIL_HOST=mailpit` + `MAIL_PORT=1025` (dev mail goes to Mailpit UI at `http://localhost:8025`), `QUEUE_CONNECTION=sync` (Filament's password-reset and email-2FA notifications are `ShouldQueue` — with the `database` queue and no worker they silently pile up in `jobs`).
- Storage symlink `public/storage -> storage/app/public` must exist (`php artisan storage:link`).

## Domain model

- `Project` (`projects`): slug, title, short_description, description, print_notes, is_published, published_at.
  Relations: `images()` (sorted by sort_order), `files()`, `featuredImage()` (hasOne where is_featured=true).
- `ProjectImage` (`project_images`): path_large, path_thumb, alt, is_featured, sort_order.
- `ProjectFile` (`project_files`): label, file_type (stl|freecad|py|other), path, sort_order.

## Media handling — critical flow

**Read this before touching image/file upload code.** This flow was debugged extensively; the pieces depend on each other.

1. **Admin upload (images):** `ImagesRelationManager` uses `FileUpload::make('path_large')` with `->disk('public')->directory('projects/images')`. Filament itself stores the file and dehydrates a **string path** into the model (never an `UploadedFile` in this flow).
   - On the edit form the field starts empty (`formatStateUsing(fn () => [])`) and is skipped when no new file (`dehydrated(fn ($state) => filled($state))`) — this avoids an infinite preview spinner and `path_large cannot be null` DB errors.
   - Current image preview is a `Placeholder` rendering `resources/views/filament/project-image-preview.blade.php` with relative `/storage/...` URLs + `?v=<timestamp>` cache-buster.
2. **Observers do the bookkeeping** (`app/Observers/ProjectImageObserver.php`, registered in `AppServiceProvider`):
   - `creating`: generates `path_thumb` via `ProjectMedia::makeThumb()` when missing; enforces single featured image per project.
   - `updating`: when `path_large` changed (string), deletes old large+thumb files from disk and regenerates the thumb; keeps featured flag unique.
   - `deleting`: removes both files from disk.
3. **`app/Support/ProjectMedia.php`**: `storeImage(UploadedFile)` (used by model mutator for non-Filament paths), `makeThumb(string $largePath)` (copies large -> `-thumb` suffix path), `storeFile(UploadedFile)`.
   - NOTE: thumbs are currently plain copies — no resize/WebP conversion yet (plan.md §7 wants 1200x900 large + 300x300 crop thumb in WebP; not implemented).
4. **Files:** `FilesRelationManager` uses `->saveUploadedFileUsing()` returning a **string** path (`ProjectMedia::storeFile`). `saveUploadedFileUsing` MUST return string|null — returning an array throws `storeFileName(): Argument #1 must be string`.
5. **Public display:** project list prefers `featuredImage->path_thumb`, falls back to first image. URLs built as `asset('storage/' . $path)`.

## Filament 5 gotchas (namespaces changed vs. most online examples)

- Form signature: `public function form(Schema $schema): Schema` — use `Filament\Schemas\Schema`, **not** `Filament\Forms\Form`.
- Layout components moved: `Filament\Schemas\Components\Section`, `Filament\Schemas\Components\Group` (NOT `Filament\Forms\Components\...`).
- Utilities: `Filament\Schemas\Components\Utilities\Get`.
- Table row actions: `->recordActions([...])`; actions come from `Filament\Actions\{CreateAction,EditAction,DeleteAction}`.
- Resource typed statics, e.g. `protected static string|\BackedEnum|null $navigationIcon`.
- Auth is Filament-native: panel uses `->login()`, `->passwordReset()`, `->profile()` and `->multiFactorAuthentication([AppAuthentication::make()->recoverable(), EmailAuthentication::make()])` (`AdminPanelProvider`). There are NO custom auth routes. `User` implements `FilamentUser` (`canAccessPanel()` returns `true` — every account is an admin; required outside `local` env) plus the MFA contracts `HasAppAuthentication`, `HasAppAuthenticationRecovery`, `HasEmailAuthentication` backed by columns `app_authentication_secret` (encrypted), `app_authentication_recovery_codes` (encrypted:array), `has_email_authentication` (bool).

## Key files map

```
app/Filament/Admin/Resources/ProjectResource.php              # project form (description/print_notes full-width)
app/Filament/Admin/Resources/ProjectResource/RelationManagers/
  ImagesRelationManager.php                                   # image upload/replace/featured UI
  FilesRelationManager.php                                    # downloadable files UI
app/Observers/ProjectImageObserver.php                        # file cleanup + thumb regen + featured uniqueness
app/Observers/ProjectFileObserver.php                         # file cleanup on delete
app/Support/ProjectMedia.php                                  # storage helpers
app/Http/Controllers/ProjectController.php                    # public list/detail
resources/views/projects/{index,show}.blade.php               # public pages
resources/views/filament/project-image-preview.blade.php      # admin thumb preview partial
database/seeders/DatabaseSeeder.php                           # idempotent admin + sample data
docker/php/Dockerfile                                         # PHP image: TMPDIR, logging, storage/tmp
```

## Routes

- `/` home, `/projekty` list, `/projekty/{slug}` detail (published only, 404 otherwise)
- `/admin/...` Filament panel, incl. auth pages: `/admin/login`, `/admin/password-reset/{request,reset}`, `/admin/profile` (2FA setup)

## Conventions

- Log every meaningful shell command with a short comment in `commands-log.md`.
- Record project decisions and progress in `plan.md` (§11 stage checklist).
- Seeders must stay idempotent (`updateOrCreate`).
- Media files live on the `public` disk under `projects/images` and `projects/files`; always delete replaced/removed files (observers handle this — don't bypass them with raw queries).

## Known gaps / roadmap (from plan.md)

- Image processing: resize to 1200x900 (large) + 300x300 crop (thumb), WebP output — currently plain copies.
- Gallery lightbox: GLightbox planned, not integrated.
- WYSIWYG: Trix planned for description/print_notes (currently plain textareas).
- Home page hero/content, optional `settings` table.
- Future: S3-ready storage migration.
