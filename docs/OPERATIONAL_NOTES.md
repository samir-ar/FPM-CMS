# Operational Notes

Practical, non-obvious knowledge about how this CMS actually behaves — things you'd otherwise only discover by debugging.

## Internal Election's Excel permitted-voters upload is a full replace, not a merge

`InternalElectionController::update()` runs `InternalElectionPermission::where('election_id', $internal->id)->delete();` **before** `Excel::import(new InternalElectionAllowedVotersImport(...), ...)` — even though the import class itself does a genuinely additive, chunked `upsert()`. Uploading a file containing only one member therefore wipes every other permitted voter for that election first. This caused a real incident (2026-10-28, ~35,000 voters wiped down to 1, recovered from a DB backup). **This is intentional, confirmed behavior** (matches the National Council Poll's own re-upload convention) — not a bug to patch. For adding/removing one voter at a time without touching the rest of the list, use the "Add a single voter by Member ID" field and the search+delete controls on the same Edit Election page instead of re-uploading Excel.

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

## Admin permissions (as of the Profile system, 2026-09-18)

Before this, the "which pages can an admin see" checkboxes (`admins_pages` pivot, `User::hasPage()`) were **cosmetic only** — every admin route required nothing stronger than `auth:admin`, so any admin could open any admin URL directly regardless of their checkboxes. This is now a real, enforced Profile system:

- A **Profile** (`profiles` table) holds a None/View/Full level per top-level page (`profile_permissions`), plus optional per-action grants for a few pages (`extra_actions` JSON column — currently just Check-In Events' "add attendance"/"export").
- `CheckPagePermission` middleware (`page-perm:<page_id>,<view|full>` or `page-perm:<page_id>,action:<name>`) actually blocks the request server-side if the admin's assigned Profile doesn't grant enough — not just a hidden sidebar link.
- Every admin needs a Profile assigned (Administrators → edit admin → Profile dropdown) or they're **fully blocked** from every permission-checked route. This is deliberate (secure-by-default for new admins), not a bug.
- Manage Profiles at **Administrators → Profiles**.
- **There are two separate, unrelated "create an admin" forms** — `Administrators → All Administrators → Add Admin` (`AdminsController`) and `Administrators → Add Account` (`ProfileController::addUserForm`/`addUser`, an older/legacy entry point). Both now have the Profile dropdown, but if a third one ever turns up, check it too — nothing enforces there's only one place admins get created.

## Check-in events — inactive events block their own attendance

A check-in event's منشط/غير نشط (`is_active`) toggle isn't just cosmetic on the admin side either: an inactive event's attendance page can't be opened, searched, exported, or added to (`CheckinEventsController::blockIfInactive()`, checked in every one of those 4 actions) — regardless of the admin's Profile permission level. This mirrors a rule the mobile app's own QR-scan flow already enforced (`ApiController::checkInMember`'s validation already required `is_active=1`); the admin side was simply missing it until 2026-09-18.

## Archive media storage — images vs. PDFs/videos

`MediaController` uploads PDFs/videos via `FileTrait::moveFile()` (S3-aware, respects `FORCE_S3_STORAGE`), but historically uploaded **images** via `copyFile()` (local-disk only, never S3) — while the mobile API (`ApiController::getAllAlbums`/`getAlbum`) always builds S3 URLs for archive media regardless of type. Fixed 2026-09-18 to use `moveFile()` for images too.

**Separate, still-open issue as of this writing**: even with that fix, a freshly-uploaded file can come back `403 Forbidden` from S3 — the bucket (`fpm-web-files`) appears to reject public-read on new objects regardless of what ACL is requested at upload time (older files still work, meaning they were made public a different way in the past, likely a manual AWS-console action at some point). This needs an AWS Console check (S3 → `fpm-web-files` → Permissions → Block Public Access) — it can't be fixed from the app's code.

## Server-level limits worth knowing

- **Staging's nginx had no `client_max_body_size` set** (defaults to ~1MB) and **PHP-FPM was capped at `upload_max_filesize=2M`/`post_max_size=8M`** — far too small for real photo uploads. Bumped to 25M/20M directly on the server. This is server config, not tracked in this repo — if staging ever gets rebuilt, redo this.
- Local WAMP's own PHP is already generous (`upload_max_filesize`/`post_max_size` = 512M) — this limit was staging-specific.

## `shortVoterName()` — "الله" never stands alone as a Lebanese surname

`ApiController::shortVoterName()` (district voters list) drops father/grandfather middle names, keeping first + family name. It already special-cased `ابو`/`ابي`-style compound prefixes (`ابو خليل`, `ابي زيد`), but missed compound surnames ending in `الله` (`عطا الله`, `نصر الله`, `فضل الله`, `جار الله`, ...) — found live via a real voter ("اسامه جبرايل عطا الله" was showing as "اسامه الله"). Fixed (2026-10-24) by always keeping the word before `الله` as part of the family name whenever the last word is literally `الله`, regardless of what the first half is — more general than enumerating every possible first word, since `الله` is never a meaningful surname on its own in this context. If another compound-surname pattern like this turns up, prefer this kind of "detect the never-standalone half" rule over extending a fixed-word whitelist.

## PHP core version/CVE patching — checked across all 3 environments 2026-10-24

In response to the real CVE-2026-109618/CVE-2026-109619 ("PHP2Shell") emergency disclosure (Oct 21, 2026, unauthenticated RCE, CVSS 9.2) plus the follow-up CVE-2026-115560 (session-upload-progress handling): production (PHP 8.2.35, Sury PPA) and staging (PHP 8.1.2-1ubuntu2.28, Ubuntu's official EOL-branch backport) were both already on the patched core version by the time this was checked — neither needed the core upgrade. The `session.upload_progress.enabled` mitigation for the follow-up CVE was live-checked via an actual HTTP request through each environment's real serving stack (CLI and FPM/Apache read separate `php.ini` files on both the Ubuntu boxes and local WAMP, so a CLI-only check would have been misleading) — production already had it `Off` (likely a Sury package default), staging needed it turned off (fixed directly, `/etc/php/8.1/fpm/php.ini`). Not tracked in this repo (server-level php.ini config, not code) — see [[project-next-steps]] memory for the full per-environment detail if this needs auditing again.

## Super Admin's "full access to everything" silently decays over time — needs an explicit backfill whenever new pages are added

The original 2026-09-18 migration (`2026_09_18_120200_add_profile_id_to_users_table`) granted the "Super Admin" profile Full access to every page that existed **at that exact moment** — a one-time snapshot, not an ongoing sync. Every real admin page built since (Social Links, Legislative Doc Categories, Content Engagement, User Activity — the default `/admin` dashboard itself — Store Analytics, and others) never got a corresponding grant. Found 2026-10-27 while investigating a "why isn't Super Admin full admin" question: Super Admin had grants for only 74 of 88 pages. Backfilled via a new idempotent migration (`2026_10_27_000000_grant_super_admin_full_access_to_remaining_pages`) that grants Full on any page the profile doesn't already have *any* grant for, resolved by profile **name** ("Super Admin"), not a literal `profile_id`, for the same cross-environment-safety reason as `pagePermByUrl()`. **This migration fixes the backlog but doesn't prevent recurrence** — any future new admin page still needs an explicit grant (to Super Admin, and to any other profile that should see it) or it'll be invisible to everyone except whoever's profile the next backfill happens to touch.

## A hardcoded `page-perm:<id>` literal can reference a page that doesn't exist at all, silently blocking every profile including Super Admin

Found alongside the above: the About Us route (`routes/web.php`) has been gated by `page-perm:39,full` since the original 2026-09-18 rollout, but no row with `id=39` exists in the `pages` table on **any** environment (confirmed via `git blame` — this was a wrong/stale literal from day one, not drift). Since no profile can ever hold a grant for a page id that was never real, this made `/admin/about-us` completely unreachable by anyone, Super Admin included, for over 5 weeks without being noticed (low-traffic page). Fixed by switching to `pagePermByUrl('admin.aboutUs.form', 'full')`, same as every other page fixed during the 2026-10-20 cross-environment page-id-drift work. **Lesson**: a 403 that happens even for Super Admin is a stronger signal of "this page_id reference is simply wrong" than "this profile needs a grant" — worth checking whether the referenced page id actually exists in `pages` before assuming it's just a missing-grant issue.

## Laravel Excel's write pipeline silently drops a literal numeric 0 to a blank cell

Found 2026-10-27 building the progress-dashboard Excel exports: `WithMapping::map()` correctly returns `int`/`float` `0` (verified directly via tinker — the export class's own `collection()`/`map()` output was always correct), but the written `.xlsx` cell comes back `NULL` for exactly those positions when read back with PhpSpreadsheet. Confirmed via direct cell inspection (`getCell('C2')->getValue()` returned `NULL`, data type `null`) — this isn't a display/formatting quirk, the cell genuinely has no value written. **Fix: cast every numeric value to `(string)` in `map()`.** This forces Laravel Excel to treat it as real (non-empty) content instead of collapsing a falsy-but-valid `0`/`0.0`. Worth checking for this same symptom ("blank cells where I expected 0") in any future export with counts/percentages that can legitimately be zero.

## Adding charts to a Laravel Excel export — `WithCharts`, and data labels live on `DataSeriesValues`, not `DataSeries`/`PlotArea`

Used for the progress-dashboard Excel exports (bar chart of turnout % per district/town). Key points, since the API is non-obvious and under-documented:
- Implement `Maatwebsite\Excel\Concerns\WithCharts` and return a `PhpOffice\PhpSpreadsheet\Chart\Chart` (or array of them) from a `charts()` method. Cell ranges (`"Worksheet!\$E\$2:\$E\${$lastRow}"`) need the real row count, which isn't known until `collection()` runs — cache it on a private property set inside `collection()`, since `charts()` always runs after it in Laravel Excel's pipeline.
- The default sheet title for a single-sheet `FromCollection` export is literally **`"Worksheet"`**, not `"Sheet1"` — confirmed by reading a real exported file back. Get this wrong and the chart silently references nothing.
- To show the value on top of each bar, the `Layout` object (with `setShowVal(true)` + `setDLblPos('outEnd')`) attaches to the **value `DataSeriesValues` object itself** via `setLabelLayout()` — not to `DataSeries` or `PlotArea`, which was the first (wrong) place this was tried. Confirmed by reading `Writer\Xlsx\Chart.php` directly: `$plotSeriesValues->getLabelLayout()` is what the writer actually checks.
- **No text-rotation option exists for data labels** in this library — checked the writer's XML output directly (`a:bodyPr` for data labels never gets a `rot` attribute written, unlike other chart text elements). If labels need rotating for legibility with many bars, that's a manual one-time step in Excel after opening the file (right-click a label → Format Data Labels → Text Options), not something this export can set automatically.
- **Verification that actually proves a chart works**: downloading the file and checking its size isn't enough. Read it back with `(new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->setIncludeCharts(true)->load(...)` and check `$sheet->getChartCount()` — the reader does **not** parse charts by default even though the writer wrote them correctly, so a naive read-back can wrongly suggest the chart is missing.

## MySQL's 64-character identifier limit bites long table names with auto-generated foreign key constraint names

Found 2026-10-28 creating `internal_election_district_voting_settings`: Laravel's `$table->foreign('election_state_id')->...` auto-generates a constraint name by concatenating `{table}_{column}_foreign`, which for this table name produces `internal_election_district_voting_settings_election_state_id_foreign` — 70 characters, over MySQL's 64-char identifier limit. The migration failed with `SQLSTATE[42000]: ... Identifier name '...' is too long`, but **the table itself had already been created** by the time the constraint statement failed, left behind in a partial state (confirmed via `Schema::hasTable()` returning true with no corresponding `migrations` row). **Fix**: pass an explicit short name as the second argument to `foreign()` (e.g., `$table->foreign('election_state_id', 'ievs_election_state_id_fk')->...`), and **always drop the partially-created table before re-running** a migration that failed this way — Laravel won't auto-clean it up, and a re-run would try to `CREATE TABLE` into something that already exists. Worth naming foreign keys explicitly on any future table whose name is long/compound like this one.

## Per-district voting toggle is scoped by (election_id, election_state_id) together — testing against the wrong election looks identical to "the toggle doesn't work"

Found live-testing the district-voting-settings feature (2026-10-28): the user toggled a district off for one election (id 1, "المجلس الوطني") but tested against their own account's actual access grant, which was for a *different* election (id 2) in the *same* district. Since `InternalElectionDistrictVotingSetting` keys on the (election_id, election_state_id) pair, not the district alone, the toggle correctly had zero effect on election 2 — this looked exactly like a bug ("I turned it off and the voter list still shows") until a direct DB check (`InternalElectionCandidate::where('member_id', ...)->get()` plus the disabled-settings table) showed the real election_id each row belonged to. **Diagnostic lesson**: when a per-election toggle "doesn't seem to work," check which election_id the test account's actual grant/candidacy belongs to before assuming the filtering logic is wrong — the two elections sharing the same district name/id is what makes this easy to miss.

**Separately found in the same session**: a candidate (member 202400199) correctly had zero visible voter list for election 1 despite being a real registered candidate there (`internal_election_candidates.id=7`) — not a bug, that election's own pre-existing `voter_list_visible` flag (a different, already-existing per-election on/off switch, unrelated to the new per-district one) was simply set to `0`. Worth checking this flag first whenever "candidate X can't see their voter list" comes up, before assuming it's the new per-district toggle at fault.
