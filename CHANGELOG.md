# Changelog

All notable changes to the FPM-CMS (Laravel backend) are documented in this file.

## Unreleased

### Added

- Bulk push notifications can now target specific groups via a "Groups" multi-select on the Send Notification form (`NotificationController`); leaving it empty still sends to all groups, matching the previous behavior.
- `php artisan app-users:dedupe` — cleans up legacy duplicate `app_users` rows left over from a bug fixed 2026-08-10 (soft-deleted rows were invisible to login lookup, so a phone-format mismatch spawned a new row instead of matching the existing one). Picks a canonical row per member (prefers verified + has a push token), merges any useful data from losing rows onto it, then soft-deletes the losers — never hard-deletes. Dry-run by default, `--commit` to apply. Run and verified on local (14,429 duplicate groups resolved, 17,409 rows soft-deleted, 0 remaining afterward) — not yet run on staging or production.

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
