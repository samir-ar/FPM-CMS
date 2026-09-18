# Changelog

All notable changes to the FPM-CMS (Laravel backend) are documented in this file.

## Unreleased

### Added

- Bulk push notifications can now target specific groups via a "Groups" multi-select on the Send Notification form (`NotificationController`); leaving it empty still sends to all groups, matching the previous behavior.
- **Admin Profiles & permissions system** — replaces the old per-admin page checkboxes (which only ever hid/showed sidebar links, never actually enforced anything) with a real, reusable Profile: a named set of None/View/Full permissions per page, assignable to many admins at once.
  - Real server-side enforcement (`CheckPagePermission` middleware, `page-perm:<page_id>,<view|full>`) on all 39 top-level admin pages — a "View" profile is now actually blocked from create/edit/delete/toggle actions, not just hidden from the sidebar.
  - Migration safety net: every existing admin was auto-assigned a "Super Admin" profile with Full access to every page, so nobody lost access when this shipped. New admins start with no profile (fully blocked) until deliberately assigned one.
  - Per-action grants on top of View for specific pages (currently Check-In Events): "Can add people to check-in" and "Can export to Excel" can each be independently granted without giving Full access to the whole page.
  - New "Profiles" admin screen (`Administrators → Profiles`) for creating/editing profiles and assigning them to admins.
  - The "Add Account" form (`Administrators → Add Account`, a second/older admin-creation form separate from "All Administrators → Add Admin") also gained the Profile dropdown — previously it had no way to set one at all, silently creating admins with no profile (fully blocked).

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
