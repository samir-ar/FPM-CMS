# FPM Development Workflow

## Purpose

This document defines the mandatory workflow for all future development on the FPM-Mobile and FPM-CMS projects.

Every AI assistant (Claude Code, ChatGPT, Copilot, Gemini, etc.) must follow this workflow unless explicitly instructed otherwise.

---

# Branch Strategy

Never develop directly on `main`.

## Permanent branches

```
main
develop
```

`main`

* Stable production code only.
* Every commit on `main` must be releasable.

`develop`

* Integration branch for the next release.

---

## Feature branches

Every task must be created from `develop`.

Examples:

```
feature/banner-widget
feature/forms
feature/settings
feature/profile
feature/notifications
feature/dark-mode
feature/performance
feature/offline-mode
```

Workflow:

```
main
    │
    ▼
develop
    │
    ├── feature/banner-widget
    ├── feature/forms
    ├── feature/profile
    ├── feature/settings
    └── feature/notifications
```

When a feature is complete:

```
feature/*
        ↓
merge into develop
        ↓
test
        ↓
merge develop into main
        ↓
create release
```

---

# Before Starting Any Feature

Always:

* Pull latest changes.
* Verify clean working tree.
* Create a new feature branch.
* Never work directly on `main`.

---

# Development Process

Every feature must follow exactly this sequence:

1. Investigation
2. Planning
3. Wait for approval
4. Implementation
5. flutter analyze / php artisan test (as applicable)
6. Manual testing
7. Commit
8. Push feature branch
9. Merge into develop
10. Regression testing
11. Merge develop into main
12. Create GitHub Release

Never skip approval before implementation.

---

# Commit Rules

Small commits only.

Each commit should represent one logical change.

Examples:

```
Fix login validation
Improve settings page layout
Refactor banner widget
Add profile API
```

Avoid large mixed commits.

---

# Pull Requests

Every feature should be reviewed before merging.

If working alone:

Review the changes yourself before merging into `develop`.

---

# Releases

Example versioning:

```
v1.0.0
v1.0.1
v1.1.0
v1.2.0
v2.0.0
```

Semantic Versioning:

```
Major.Minor.Patch
```

Patch

```
v1.0.1
```

Bug fixes.

Minor

```
v1.1.0
```

New features.

Major

```
v2.0.0
```

Breaking changes.

---

# GitHub Milestones

## v1.1.0

* Shared Banner Widget
* Forms
* Notifications
* Profile
* Settings Improvements

## v1.2.0

* Dark Mode
* Localization
* Performance
* Offline Mode

---

# CHANGELOG

Both repositories must contain:

```
CHANGELOG.md
```

Each release must document:

* Added
* Changed
* Fixed

Example:

```
## v1.0.0

### Added

- Membership application integration
- Shared loading widgets

### Changed

- Completed C1-C7 UI redesign
- Improved accessibility
- Improved banner consistency

### Fixed

- QR generation
- Network error handling
- Button contrast
- Back button touch targets
```

---

# FPM-Mobile

Release process:

```
feature/*
        ↓
develop
        ↓
test
        ↓
main
        ↓
tag
        ↓
GitHub Release
```

---

# FPM-CMS

Follow exactly the same workflow as FPM-Mobile.

No exceptions.

---

# AI Assistant Rules

Whenever the user says:

"Start"

or

"Let's continue"

the assistant must first:

1. Read this document.
2. Read CHANGELOG.md.
3. Read the current progress document.
4. Verify the current Git branch.
5. Verify Git status is clean.
6. Ask for approval before implementation.
7. Work on one feature at a time.
8. Run analysis/tests.
9. Summarize changes.
10. Commit.
11. Push.
12. Stop and wait for approval.

Never continue to the next feature automatically.

Always work incrementally.

Always preserve clean Git history.

Always prioritize safe, reviewable changes over speed.
