# Operational Notes

Practical, non-obvious knowledge about how this CMS actually behaves — things you'd otherwise only discover by debugging.

## Where "Groups" data comes from

Groups are **not authored locally**. The admin "Refresh Groups" button (`/admin/groups/create`, `GroupsController::create()` — the route name says "create" but it's a sync action, not a form) calls an external API:

```
https://twhsystem.org/TWHMembersAPI/TWHEPartyService.svc/CMS_GetServiceGroups?AccessToken=test1&serviceid=4
```

On click, it **truncates** the local `groups` table and reinserts everything returned by that external call. `AccessToken=test1` is a hardcoded test credential baked into `FpmApisRepository::getGroups()`, not tied to any admin session.

If you need a brand-new group that doesn't exist yet, it must be created in that external TWH/FPM system first — then "Refresh Groups" pulls it in locally.

## Bulk push notifications — no environment separation

`NotificationController` → `App\Listeners\SendPushNotification` sends real OneSignal push notifications to real, live registered users. There is **no separate dev/test OneSignal app** — the CMS `.env` (`ONE_SIGNAL_APP_ID`) and the Flutter app's production flavor (`main.dart`/`main_prod.dart`) share the exact same OneSignal App ID.

- The Flutter app's **local/staging flavors never call `OneSignal.initialize()`**, so an emulator can never receive a push regardless of what you send — testing on the emulator is a dead end for this feature.
- There's a dormant safety switch: `SendPushNotification.php` checks `env('TEST_SERVER')` and, if true, redirects every send to one hardcoded test `player_id` instead of real users. **As of this writing, `TEST_SERVER` is not set in `.env`**, so this switch is inactive — any "Send Notification" click goes out to real members.
- Group targeting (added — see CHANGELOG) lets you scope a send to specific groups instead of all members, but does not change the live-vs-test behavior above.

**Before testing this feature for real:** either set `TEST_SERVER=true` in `.env`, or be certain the selected group(s) contain no real members you don't intend to message.

## Local WAMP dev environment

- **SSL/cURL errors on outbound HTTPS calls** (`cURL error 60: SSL certificate problem: unable to get local issuer certificate`) mean the local PHP install has no CA bundle configured. Fix: set `curl.cainfo` and `openssl.cafile` in `php.ini` to a valid `cacert.pem` (one is already vendored under phpMyAdmin's Composer dependencies on this machine — no need to download a new one), then restart the PHP process serving requests.
- **Always start the local dev server with `php artisan serve`** — never hand-roll `php -S host:port server.php` from the project root, even if copying the exact command line from a running process. `server.php`'s static-asset fallback (`file_exists(__DIR__.'/public'.$uri)`) only resolves correctly when the process is launched the way Artisan's `ServeCommand` does it internally. A manual invocation silently breaks all static asset serving (`public/css`, `bower_components`, etc. all 404), which presents as a fully unstyled admin panel — easy to mistake for a CSS bug when it's actually a server-launch problem.
- **This machine's local MySQL defaults to the `MyISAM` storage engine, not InnoDB.** Any new migration that doesn't explicitly set `$table->engine = 'InnoDB';` gets created as MyISAM, which silently drops foreign-key enforcement (MyISAM ignores FK clauses entirely) until something InnoDB tries to reference it, at which point you get `SQLSTATE[HY000]: 1824 Failed to open the referenced table`. Always add the explicit engine line on new migrations here. Staging's MySQL defaults to InnoDB normally — this is a local-machine-only quirk.

## Admin permissions (as of the Profile system, 2026-09-18)

Before this, the "which pages can an admin see" checkboxes (`admins_pages` pivot, `User::hasPage()`) were **cosmetic only** — every admin route required nothing stronger than `auth:admin`, so any admin could open any admin URL directly regardless of their checkboxes. This is now a real, enforced Profile system:

- A **Profile** (`profiles` table) holds a None/View/Full level per top-level page (`profile_permissions`), plus optional per-action grants for a few pages (`extra_actions` JSON column — currently just Check-In Events' "add attendance"/"export").
- `CheckPagePermission` middleware (`page-perm:<page_id>,<view|full>` or `page-perm:<page_id>,action:<name>`) actually blocks the request server-side if the admin's assigned Profile doesn't grant enough — not just a hidden sidebar link.
- Every admin needs a Profile assigned (Administrators → edit admin → Profile dropdown) or they're **fully blocked** from every permission-checked route. This is deliberate (secure-by-default for new admins), not a bug.
- Manage Profiles at **Administrators → Profiles**.

## Archive media storage — images vs. PDFs/videos

`MediaController` uploads PDFs/videos via `FileTrait::moveFile()` (S3-aware, respects `FORCE_S3_STORAGE`), but historically uploaded **images** via `copyFile()` (local-disk only, never S3) — while the mobile API (`ApiController::getAllAlbums`/`getAlbum`) always builds S3 URLs for archive media regardless of type. Fixed 2026-09-18 to use `moveFile()` for images too.

**Separate, still-open issue as of this writing**: even with that fix, a freshly-uploaded file can come back `403 Forbidden` from S3 — the bucket (`fpm-web-files`) appears to reject public-read on new objects regardless of what ACL is requested at upload time (older files still work, meaning they were made public a different way in the past, likely a manual AWS-console action at some point). This needs an AWS Console check (S3 → `fpm-web-files` → Permissions → Block Public Access) — it can't be fixed from the app's code.

## Server-level limits worth knowing

- **Staging's nginx had no `client_max_body_size` set** (defaults to ~1MB) and **PHP-FPM was capped at `upload_max_filesize=2M`/`post_max_size=8M`** — far too small for real photo uploads. Bumped to 25M/20M directly on the server. This is server config, not tracked in this repo — if staging ever gets rebuilt, redo this.
- Local WAMP's own PHP is already generous (`upload_max_filesize`/`post_max_size` = 512M) — this limit was staging-specific.
