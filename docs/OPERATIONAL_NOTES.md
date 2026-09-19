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
