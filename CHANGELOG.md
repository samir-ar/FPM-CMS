# Changelog

All notable changes to the FPM-CMS (Laravel backend) are documented in this file.

## Unreleased

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
