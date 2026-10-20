# Changelog

All notable changes to the FPM-CMS (Laravel backend) are documented in this file.

## Unreleased

Committed and deployed to staging **and production** 2026-10-16:

### Added

- **Internal Election Votes Report** (`/admin/internal-election/{id}/votes-report`) — a full per-voter list across every district at once (Voter, Member ID, District, Town, Candidate, Voted At), with an "Export to Excel" button. District/town are resolved from `fpm_users`, not `app_users`. Deliberately not linked from any sidebar menu or the election's edit page — reached only via direct URL, same convention as the existing Vote Dispute Review page.
- **Add/Edit Representative** now auto-fills Name(English) and Name(Arabic) from the member's real `fpm_users` name once a Member ID is entered (on blur) — same convention as the photo, which already resolves live from the member's real ID. `fpm_users` only stores one (Arabic) name, so both fields are pre-filled with it as a starting point; Name(English) is still overwritten manually.
- `legislative-docs:import-batch {category_id} {source_dir} {mapping}` artisan command — bulk-imports a folder of PDFs into Legislative Docs from a JSON filename→{title, date} mapping, reusing the same upload convention as the admin form. Used to import 43 real "أسئلة للحكومة" PDFs in one run; kept as a reusable command for any future batch upload.
- `memos:import-batch {source_root} {mapping} {group_id=81}` artisan command — bulk-imports a folder of PDFs into Memos from a JSON filename→{date, name_ar, name_en, source_dir} mapping, assigns every imported memo to one group, never fires a push notification. Used to import 180 real FPM circulars/memos (تعاميم + مذكرات) in one run.

### Changed

- Admin login page's "For Support" contact info updated.

### Fixed

- **Internal Election voter-list viewer grants had no working entry point in the app.** `is_internal_election_candidate` (which gates the Home-screen district-voters tile) only checked `InternalElectionCandidate`, so a non-candidate viewer grant (shipped 2026-10-16) passed the backend auth check but had no visible way to reach it. Broadened the same flag to also check `InternalElectionVoterViewer` — fixes the gap with no app release needed, since the app only reads the existing field name.
- `getMyDistrictVoters()` applied the `voter_list_visible` toggle filter *before* checking candidate/viewer status, so an actual candidate/viewer got the misleading "this page is for candidates only" message whenever an admin paused list visibility. Now checks candidate/viewer status unconditionally first, with a distinct "list not currently available" message when that's the real cause — and the Home tile itself now also respects the toggle, disappearing entirely instead of just erroring on tap.
- Vote Dispute Review table no longer shows a Candidate column — that page is about verifying *who* voted, not *who they voted for*; the new Votes Report page covers the latter.
- **Login-time Force Update gate wasn't actually per-platform.** `RegistrationController::sendVerfication()`'s build-number check used `max(android_version, ios_version)` whenever *either* platform's Force Update flag was on, so turning on Android's alone also wrongly blocked iOS logins, ignoring iOS's own unchecked box — a real bug a user hit directly. Now checks only the submitted platform's flag/version when the app sends one (see the fpm-flutter changelog for the matching app-side fix), falling back to the old `max()` behavior for already-installed apps that predate that field.

Committed and deployed to staging **and production**, 2026-10-01 through 2026-10-16 (backfilled retroactively — see `project-next-steps` memory for the full day-by-day narrative, including the app-store release cycles this work shipped alongside):

### Added

- **Internal Election — full selfie-verification feature.** Per-election `requires_selfie` toggle; a live front-camera selfie is captured at vote time and uploaded directly to S3 via a presigned URL (new `POST internal-election-selfie-upload-url` endpoint), never through the app server. `internalElectionVote()` verifies the claimed file actually exists in S3 before accepting the vote, rejecting a made-up filename outright. New **Vote Dispute Review** admin page (`/admin/internal-election/{id}/votes`, district-filtered) shows each vote's captured selfie next to the voter's real on-file photo for manual review.
- **Internal Election — device-ID fraud detection**: `device_id` captured on every vote, with a "reused device ID" highlight on the Vote Dispute Review page when the same device cast votes for more than one distinct member.
- **Internal Election — non-candidate voter-list viewer grants**: a new `internal_election_voter_viewers` table + dedicated admin page (`/admin/internal-election/{id}/voter-viewers`) lets an admin grant a specific non-candidate (e.g. campaign staff) the same district-scoped voter-list access a candidate gets.
- **Internal Election — independent `voter_list_visible` toggle** (default on), separate from an election's own published/`is_active` status, letting an admin pause the voter list (for candidates and granted viewers alike) without touching whether the election itself is published.
- **Internal Election — two separate build-number enforcement gates**: a per-request minimum build number for all 4 voting-related endpoints (`can-i-vote`, `get-internal-election-candidates`, `internal-election-vote`, `my-district-voters`), and a login-time (OTP-request) check in `RegistrationController::sendVerfication()` comparing the submitted build against the configured minimum — the latter needed no app release to take effect, since the app was already sending the field for an unrelated purpose.
- **Internal Election — رديف (alternate) badge + age-based tie-break** in the results report when vote counts are equal, plus a hidden-by-default birthdate column (toggle button) and the "Export" button renamed to "Results".
- **Internal Election — bulk permitted-voters import rewritten** from per-row `firstOrCreate()` (~70,000 queries, caused a real 504 timeout on a ~35,100-row file) to a chunked bulk `upsert()` — same import now completes in ~1.5 seconds.
- Public `/news/{id}` page + `.well-known/assetlinks.json`/`apple-app-site-association` routes — the web fallback for Android App Links / iOS Universal Links when a shared article link is opened outside the app.
- Custom CMS dashboard combining Android (Play Developer Reporting + classic stats bucket) and iOS (App Store Connect Analytics) store analytics in one place (`AppStoreConnectService`, `GooglePlayStatsService`, `GooglePlayVitalsService`, `AppAnalyticsDashboardService`).
- User Activity dashboard: all 4 stat tiles (DAU/WAU/MAU/YAU) are now clickable, opening a popup listing every member counted in that tile with cascading district→town filters.
- CMS admin sidebar reorganized from ~30 flat items into 14 categories.
- `refreshPermissions()` now also accepts an optional `player_id` to resync a device's OneSignal subscription ID after login, since the original one-time capture at login often ran before OneSignal had finished registering.

### Fixed

- `appVersions()` API endpoint was returning hardcoded dummy data instead of the real `Setting` values — the whole app-update-check feature was dead until fixed.
- `PushNotificationTrait` called `str_random()`, a Laravel helper removed in the version this app now runs — any push notification for a post with an image attached hard-crashed.
- Guest access to Events/News/Polls 500'd on production: `show_for_guest` existed locally as a hand-added column but was never captured in a real migration, so it never reached production.
- `getBiographyDetails()` only matched a biography to one specific `persons` row — a person listed under multiple election categories showed no biography for the others. Now falls back to matching by translated Arabic name.
- User Activity dashboard's "Last Activity" froze at a member's first visit of the day instead of updating (`insertOrIgnore()` → `upsert()`).
- Morris.js trend charts (User Activity, then Content Engagement's "Daily Likes") rendered as a single squished point — fixed with `parseTime: false`.
- `SetLanguage` middleware crashed (500) on any `v2/` API route hit directly from a real browser, since it passed a raw `Accept-Language` header straight to `App::setLocale()` — browsers send quality-value syntax (`en-US,en;q=0.9,...`) that Symfony's locale validator rejects outright. Fixed with `Request::getPreferredLanguage()`.
- Content Engagement dashboard: the date filter was blank by default (meant "all time") instead of defaulting to today; "Top 20 Most Shared Articles" showed the raw translatable-JSON string instead of the resolved title whenever a date filter was active.
- Some news articles had `source_image` accidentally set to the app's own Play Store icon file, making link previews show the app logo instead of no image.
- Permitted-voters admin table showed blank names for anyone who had never opened the app (`app_users` lookup only, no fallback to `fpm_users`) — same pagination gap fixed at ~15,000 rows (now paginated, 25/page).
- `internal_election_votes` had no unique constraint on `(user_id, internal_election_id)` — a real double-vote vulnerability, now constrained with graceful error handling.
- `AdminsController::pageCheckboxes()` only rendered top-level pages, so saving any admin's permissions via the edit form silently wiped all their granular child-page grants; `AdminsController::destroy()` threw a foreign-key violation deleting an admin with existing page permissions; `FormTrait.php`'s checkbox handling broke on a falsy `page_id=0`.

Committed and deployed to staging **and production** 2026-09-29/30 (the first time this project's recent work reached production):

### Added

- **National Council Poll — admin-side hardening.** The voter-list Excel import previously defaulted a missing `weight` column to 1 silently; now requires both Member ID and Weight explicitly, failing with a clear Arabic message + a downloadable sample template instead of a raw crash. Poll save + import now happen in one DB transaction, so a bad file can't leave an orphaned poll or wipe an existing voter list on re-upload.
- **Internal Election — per-election voter allowlist**, replacing the global `Allowed_to_vote` column entirely. New `internal_election_permissions` table (`election_id` + `member_id`), uploaded inline on the election's own create/edit form (previously required no admin UI at all beyond a direct DB edit). Migration backfills one permission row per (currently-active election × previously-`Allowed_to_vote=1` member) so nobody lost voting access at cutover. Also built real `edit()`/`update()` controller methods for the first time — the routes existed but had nothing behind them.
- `database/migrations/2026_09_30_120000_backfill_pages_missing_from_manual_local_additions.php` — 14 real admin sidebar pages (Political Work, Municipalities, Mukhtars, Internal Org, Legislative Docs, National Plans, Competency Platform, Business Types, Community Posts, Directory Members, Check-In Events, Social Links, Legislative Doc Categories, Community parent) had only ever been added to local's `pages` table manually through the admin UI, never via a migration — so they never reached staging/production despite the underlying feature code being deployed. Idempotent by `url`.

### Fixed

- `getPermittedTable()` (both National Council Poll and Internal Election admin "who's permitted" views) joined directly to `app_users` on `member_id`, fanning one real permission row into N displayed rows whenever that member has N duplicate `app_users` accounts — fixed to look names up separately.
- Internal Election's admin list had a broken Edit link — the `<a>` tag had no icon/text inside it and was never closed before the delete link opened, so there was nothing visible to click even though the edit page itself worked fine when reached directly by URL.
- Production's `.env` had a literal typo, `CACHE_DRIVER=file\`` (stray backtick) — silently tolerated by the old Laravel version running there, hard-crashed under Laravel 10's stricter cache-store validation. Found and fixed during the production PHP 7.4→8.2 cutover.

### Infrastructure

- **Production cutover**: PHP upgraded from 7.4-only to 8.2-FPM, full `develop` branch code deployed, 73 migrations run against the real database — done live with zero downtime beyond a few seconds and zero data loss, full backups throughout. Full playbook in project memory (`reference_production_server.md`) if this needs repeating for a future major deploy.
- CloudFront CDN set up in front of the `fpm-web-files` S3 bucket (staging only so far) — fixes a recurring client-side image-loading flakiness traced to DNS resolution issues specific to the raw S3 domain.

### Added

- Bulk push notifications can now target specific groups via a "Groups" multi-select on the Send Notification form (`NotificationController`); leaving it empty still sends to all groups, matching the previous behavior.
- `php artisan app-users:dedupe` — cleans up legacy duplicate `app_users` rows left over from a bug fixed 2026-08-10 (soft-deleted rows were invisible to login lookup, so a phone-format mismatch spawned a new row instead of matching the existing one). Picks a canonical row per member (prefers verified + has a push token), merges any useful data from losing rows onto it, then soft-deletes the losers — never hard-deletes. Dry-run by default, `--commit` to apply. Run and verified on local (14,429 duplicate groups resolved, 17,409 rows soft-deleted, 0 remaining afterward) — not yet run on staging or production.
- `idx_member_id` index on `fpm_users.MemberId`, added manually (not yet via migration) to local dev and staging on 2026-09-20 to make cross-environment joins/updates on `MemberId` performant. Still needs a proper migration and a production rollout — see `docs/OPERATIONAL_NOTES.md`.
- `fpm_users.LastPositionUnitName` column (`varchar(250)`, `utf8mb4_unicode_ci`, nullable, positioned after `LastUnitPosition`), added manually (not yet via migration) to production, staging, and local on 2026-09-22. Matches the existing `LastUnitPosition`/`NashatUnit`/`NoufousUnit` column shape. Not yet populated or wired into the API/app — see `docs/OPERATIONAL_NOTES.md`.

### Changed

- `fpm_users.LastUnitPosition` refreshed from a newer TWH member dump (imported locally as scratch table `fpm_users_2026`) — 469 rows had a stale value locally; same update applied to staging on 2026-09-20. Not yet applied to production.

Uncommitted, built 2026-09-24 in a local working session — not yet on any branch/PR, local database only:

### Added

- **New admin dashboard: User Activity report** (`UserActivityController`, now the default `/admin` landing page, replacing the old placeholder). Tracks real per-member activity via a new `user_activity_logs` table (one row per member per calendar day, written via `insertOrIgnore` from `MyAuthV2` middleware on every authenticated request, plus at login success) — not reusing `app_users.updated_at`, which turned out to be an unreliable proxy (gets bumped by unrelated things like the dedupe command's merge step). Shows DAU/WAU/MAU/YAU, a 30-day daily-active trend, a rolling 30-day "periodically active" trend (so members who visit every couple of weeks don't look "gone" on their off days), engagement-frequency buckets (power/regular/casual), and most-engaged/dormant member lists. Only tracks activity from 2026-09-24 forward — no retroactive history.
- **New `social_links` table + admin CRUD** (`SocialLinksController`, `/admin/social-links`) — powers the app's new Talk To Us page. Icon is picked from a 12-option visual Font Awesome picker (`icon_key` column) instead of an uploaded image, using a select2-icon-picker component that already existed in the admin theme but had never actually been wired up anywhere (also fixed a small pre-existing bug in it: `$('.select2').not('font-awesome')` was missing a leading dot, so it never actually excluded font-awesome selects from the plain init). New public endpoint: `POST api/v2/social-links` (active links only).
- `directory_members.member_id` column — links a directory listing back to the member's real `app_users`/`fpm_users` record. Added to the DB, the admin list/create/edit forms, and the Excel import template/parser (new column inserted after Name — **any previously-downloaded template file needs re-downloading**, since this shifts every column after it by one position).
- `directory_members.is_active` column with an admin enable/disable toggle (same pattern as `checkin_events.is_active`) — `getDirectoryMembers` now only returns active listings to the app. Paired with a new member-facing self-service toggle: `POST api/v2/my-directory-listing-status` / `POST api/v2/toggle-directory-listing`, matched by `member_id`, surfaced in the app's Settings page.
- `ApiController::refreshPermissions` now also returns `last_position_unit_name` (from `fpm_users.LastPositionUnitName`) — the endpoint already returned `last_unit_position`/`nashat_unit`/`noufous_unit` but had never been updated to include this newer column.
- `FormTrait::drawHtml('icon-select', ...)` — new reusable field type for a Font Awesome icon picker, used by Social Links; available for other admin forms going forward.

### Fixed

- A stale orphaned `pages` row (id 78, "Profiles", `url = admin.profiles.index`) was breaking the *entire* admin panel with a 500 error, on any page, for any admin — leftover from the still-unmerged `feature/admin-profiles-permissions` branch's migrations having run against this same local database at some point. The sidebar renders every page row's URL on every page load, so one bad route name took the whole panel down. Removed the row (and its `admins_pages` grants) — that branch's own migration will recreate it correctly whenever it's actually merged.

Uncommitted, built 2026-09-26 in a local working session — not yet on any branch/PR, local database only:

### Added

- **Member status login-block + auto-logout**: an inactive member (`fpm_users`/`app_users`.`member_status = 0`) is now blocked from logging in (`RegistrationController::sendVerfication()`, checked before the OTP SMS is sent — no SMS goes out for a blocked account) and is force-logged-out on their next authenticated request if already signed in (`MyAuthV2` middleware throws a 401 with the message "الحساب مغلق. يرجى التواصل مع أمانة سر التيار الوطني الحر."). Verified live end-to-end against a real test account.
- **Internal Election — full feature build**, previously backend-only since ~2021 with no app UI. Genuine multi-election support (any number of elections can be published/open at once, each with its own `closes_at`). Candidates can belong to one or more districts via a new `internal_election_candidate_states` pivot, and are added either by searching existing FPM members or entered manually (`first_name`/`father_name`/`family_name` split, optional local photo). Voting is strict one-member-one-vote. New endpoints: `GET can-i-vote`, `GET get-internal-election-candidates`, `POST internal-election-vote`.
- **`Allowed_to_vote` override column** — `fpm_users.Allowed_to_vote` / `app_users.Allowed_to_vote` (`tinyint`, default 0, migration `2026_09_26_205647_add_allowed_to_vote_to_fpm_users_and_app_users_tables`). When set, lets a member vote in an internal election regardless of district/candidate matching — still can't vote twice, election still has to be open. Wired into `ApiController::canIVote()` / `getInternalElectionCandidates()` / `canIVoteFor()`. Not yet exposed in the admin UI.
- **Per-poll "Guest Access" toggle** (`PollsController::toggleGuestAccess`) — polls stay members-only by default; a flagged poll becomes visible and votable to guests, including anonymous voting via a persisted device UUID (`guest_poll_votes` table).
- **Internal Election results/report page rebuilt** (`/admin/internal-election/export/{id}`): only shows districts the election actually has candidates in (previously rendered an empty section for every district nationwide), lists every candidate per district including zero-vote ones, highlights the top-3 ranked candidates once voting has started, shows each candidate's photo, and has a collapsible per-district filter (`?states[]=`, native `<details>`/`<summary>`, no JS) to narrow the report.

### Changed

- Events and Archive are now genuinely guest-accessible (the routes existed before but weren't wired to guest-tolerant middleware); Events additionally respects a per-item `show_for_guest` flag.

### Fixed

- Internal Election report page: a `margin-right:50%; transform:translateX(50%)` centering hack that only produced correct math under LTR broke this page's `dir="rtl"` layout (sideways overflow + horizontal scrollbar) — replaced with plain `margin:auto` centering. A decorative flanking-line pair around the title, built with absolutely-positioned `::before`/`::after`, rendered far off-screen instead of hugging the title — replaced with a plain flexbox row. `text-align` was found to have zero effect on `<th>`/`<td>` cells in this RTL `table-layout:fixed` table (root cause not identified) — worked around by moving alignment onto an inner wrapper `<div>` per cell instead of the cell itself. The page's `asset()`-generated logo/CSS URLs pointed at the wrong origin when served via a manual `php -S` process instead of `php artisan serve` (`APP_URL` mismatch) — switched to root-relative paths.

### Fixed

- Archive images uploaded via the admin panel now actually reach S3 (`MediaController` switched from `copyFile()` to the S3-aware `moveFile()`, matching PDFs/videos) — previously they saved local-disk only while the mobile API already built S3 URLs for them unconditionally, so any image added since the last manual S3 sync showed broken in the app. Also fixed a bug in the same code: `store()` was saving the thumbnail's filename into `file_name` instead of the full image's own filename.
- `FileTrait::removeFile()` now also attempts an S3 delete (previously local-unlink only) when a media item is removed.

### Known issue (not yet resolved)

- Even with this fix, newly-uploaded images may still return `403 Forbidden` from S3 — the bucket's "Block Public Access" setting appears to reject public-read on new objects regardless of ACL. This needs an AWS Console check (S3 → `fpm-web-files` → Permissions), which is outside what this fix can address in code.

### Fixed

- Adding check-in attendance for a member with a large `MemberId` (e.g. `90004300142`) threw a 500 error (`SQLSTATE[22003]: Numeric value out of range`) — `event_attendance.member_id` was created as `unsignedInteger` (max ~4.29 billion) instead of matching `fpm_users.MemberId`'s `bigint`. Widened the column to `bigint unsigned`.

## v1.0.0

First production release after completing the Critical UI redesign, accessibility improvements, backend QR fixes, and membership application flow.

### Added

- `App\Services\QrCodeGenerator` — shared helper for generating base64-encoded PNG QR codes.

### Changed

- QR code generation switched from `simplesoftwareio/simple-qrcode` (Imagick-based) to `endroid/qr-code` (GD-based), since the Imagick PHP extension is not available in this environment.
- `UserRepository::addUser()` now generates a QR code on a member's first registration, closing a gap where new members never received one.
- The existing re-verification branch in `RegistrationController.php` now uses the same shared QR generation helper, removing duplicated logic.

### Fixed

- QR code generation, which previously failed silently (Imagick missing) and left members with no scannable QR code at all.
