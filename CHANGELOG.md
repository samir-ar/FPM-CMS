# Changelog

All notable changes to the FPM-CMS (Laravel backend) are documented in this file.

## Unreleased

### Added

- Bulk push notifications can now target specific groups via a "Groups" multi-select on the Send Notification form (`NotificationController`); leaving it empty still sends to all groups, matching the previous behavior.

### Fixed

- Archive images uploaded via the admin panel now actually reach S3 (`MediaController` switched from `copyFile()` to the S3-aware `moveFile()`, matching PDFs/videos) — previously they saved local-disk only while the mobile API already built S3 URLs for them unconditionally, so any image added since the last manual S3 sync showed broken in the app. Also fixed a bug in the same code: `store()` was saving the thumbnail's filename into `file_name` instead of the full image's own filename.
- `FileTrait::removeFile()` now also attempts an S3 delete (previously local-unlink only) when a media item is removed.

### Known issue (not yet resolved)

- Even with this fix, newly-uploaded images may still return `403 Forbidden` from S3 — the bucket's "Block Public Access" setting appears to reject public-read on new objects regardless of ACL. This needs an AWS Console check (S3 → `fpm-web-files` → Permissions), which is outside what this fix can address in code.

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
