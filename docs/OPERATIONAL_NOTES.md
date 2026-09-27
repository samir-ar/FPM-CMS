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

## `app_users` has legacy duplicate rows per member — `fpm_users` is the real source of truth

`app_users` is not authoritative membership data — it's a side-effect table created the first time someone logs in via the mobile app (device/push token, profile-completion fields, QR code). `fpm_users` (synced nightly from TWH) is the real membership record. A bug fixed 2026-08-10 (soft-deleted `app_users` rows were invisible to the login lookup, so a phone-number-format mismatch would spawn a brand-new row instead of matching the existing one) left behind ~14,429 members with duplicate active rows — that bug is fixed, so this is a fixed, non-growing legacy cleanup problem, not an active leak.

`php artisan app-users:dedupe` (dry-run by default, `--commit` to apply) cleans this up: per member, keeps the row that's verified with a push token (falling back sensibly if neither exists), merges any useful field from the losing rows onto it, and **soft-deletes** (never hard-deletes) the rest. Run and verified on local as of 2026-09-19 — not yet run on staging or production; each of those needs its own explicit go-ahead given the scale (40,000+ real members) and that push notifications have no test/dev separation (see above).

Separately, there are also `app_users` rows with no matching `fpm_users` record at all ("orphaned," not "duplicate") — the dedupe command deliberately does not touch these; that's a different decision.

## Windows `mysql`/`mysqldump` client silently mangles Arabic text without `--default-character-set=utf8mb4`

On Windows dev machines, running the WAMP-bundled client (`wamp64/bin/mysql/mysql8.3.0/bin/mysql.exe`) without explicitly passing `--default-character-set=utf8mb4` causes any Arabic (or other multi-byte UTF-8) column value to come back as literal `?` characters when the output is printed or redirected to a file — even though the table and the data stored in it are correctly `utf8mb4`. The corruption happens client-side during output conversion, not in the database itself.

This bit us generating a data-sync script for `fpm_users.LastUnitPosition` (Arabic job-title text): the exported SQL file had every value turned into `?????`, and it wasn't caught until after that bad file had already been applied once to staging. Always pass `--default-character-set=utf8mb4` on this client for anything touching non-ASCII columns (ad-hoc `SELECT` output redirected to a file, `mysqldump`, etc).

## `fpm_users` needs an index on `MemberId` for cross-environment joins

`fpm_users.id` is a local auto-increment primary key that does not correspond across environments — local, staging and production each have their own numbering and different total row counts. `MemberId` (the real TWH member identifier) is the correct join key across environments, but there is no index on it by default.

Joining/updating two ~40k-row copies of `fpm_users` without an index on `MemberId` was observed taking 12+ minutes before being killed (local InnoDB buffer pool is only 256MB, smaller than the ~770MB table, so the unindexed nested-loop join thrashed against disk). Adding `ALTER TABLE fpm_users ADD INDEX idx_member_id (MemberId);` made the identical operation instant. This index was added manually (not via a Laravel migration) to local dev and staging as of 2026-09-20 — a proper migration should be created so this doesn't silently disappear in a fresh environment, and it still needs to be applied to production.

## `fpm_users.LastPositionUnitName` — manually added column, no migration yet

Added directly via SQL (DBeaver on production/staging, `mysql` CLI on local) on 2026-09-22 — `varchar(250)`, `utf8mb4_unicode_ci`, nullable, `AFTER LastUnitPosition`, matching the shape of the existing `LastUnitPosition`/`NashatUnit`/`NoufousUnit` columns. Applied to production, staging, and local, in that order, and verified on each via `SHOW FULL COLUMNS`.

Like `idx_member_id` above, this has no Laravel migration backing it yet, and the column isn't populated or exposed through the API/app. A real migration should be written to cover this alongside `idx_member_id` and the three sibling columns, so a fresh environment doesn't silently miss them.

## Staging environment details

- Staging runs on its own VPS (hostname `vmi2866881`), not on the production RDS instance — its `.env` has `DB_HOST=127.0.0.1`, i.e. MySQL is co-located on the same box as the app.
- Staging's DB user is `fpmadmin` (distinct from local dev's `root` and production's RDS master user).
- Staging's phpMyAdmin (behind nginx) times out with a 504 on imports of even a few MB (a ~3MB / 40k-row multi-statement SQL import failed). For anything non-trivial, apply SQL directly via the `mysql` CLI over SSH on that box instead of phpMyAdmin's web import.
