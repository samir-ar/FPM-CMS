# How we update the CMS (local -> staging -> production)

The working recipe as of 2026-11-26. Follow it as written; history of every release is in `OPERATIONAL_NOTES.md` and `../CHANGELOG.md`.

## Rules that never change

- **Production is changed only by commands the owner pastes.** Never SSH or SCP to production, not even to read.
- Do only what was asked. If something else looks wrong, say it and ask before touching it.
- One question at a time, in plain English. Show a picture before finalising a visual change.
- Build and test **locally** first. Production only when the owner says so ("production").
- Once the owner says it works, or asks to deploy: staging + GitHub + notes are updated without asking again.
- Never write passwords, tokens or keys into notes, commits or chat. Name the `.env` key instead.

## Where things are

| | |
|---|---|
| Local | `c:\wamp64\www\fpm_mobile_app\CMS`, PHP `C:\wamp64\bin\php\php8.2.18\php.exe` |
| GitHub | `samir-ar/FPM-CMS`, current branch `feature/national-council-session` (not merged to develop/main) |
| Staging | `root@157.173.108.207`, `/var/www/staging`, `php` (8.1), `php8.1-fpm`, https://staging.idea-holic.com (SSH key works from this PC) |
| Production | AWS, `/home/fpm/public_html`, **`php8.2`** (never bare `php`), `php8.2-fpm`, nginx, MySQL on RDS, https://fpmmobile.com |
| Owner's Desktop | `C:\Users\sabou\OneDrive - Circlegl\Desktop` (the OneDrive one). Put only the file that was asked for there. |

## 1. Local

1. Change the code, add or update tests in `tests/Feature/NationalCouncil` (extend `CouncilTestCase`: local database only, rolled back).
2. Run: `php vendor/bin/phpunit tests/Feature` (one known failure: `ExampleTest` returns 404 because `APP_URL` has a folder prefix).
3. Traps: local `.env` has `FORCE_S3_STORAGE=true` (uploads hit the **real** bucket, tests must `Storage::fake('s3')`); MyISAM tables are not rolled back; tests must not send real push notifications; a DataTables list called twice in one test needs `forgetInstance('datatables.request')`.
4. Preview of an admin page: render it through its controller into a static HTML file in the scratchpad and screenshot it with headless Chrome (`--disable-web-security`, fresh `--user-data-dir`); sample data only inside a rolled-back transaction.

## 2. Production package

1. Only the changed files, relative to the CMS root:
   `tar -czf "<Desktop>/cms_prod_<topic>_YYYYMMDD.tar.gz" file1 file2 ...`
2. Record for the owner: size, `sha256sum`, and for each file the fingerprint production should have **now** (`git show <old-commit>:file | tr -d '\r' | md5sum | cut -c1-8`) and "not there" for new files. `routes/web.php` goes as a whole file, so its fingerprint must match.
3. Owner uploads the file to **`/tmp`** (MobaXterm).

### Step 1: check (changes nothing)

```
cd /home/fpm/public_html && ls -l /tmp/PKG && sha256sum /tmp/PKG && for f in FILES; do if [ -f $f ]; then echo "$f $(tr -d '\r' < $f | md5sum | cut -c1-8)"; else echo "$f not there"; fi; done
```

Compare every value with the expected table. Any difference = stop and find out why.

### Step 2: apply (one single `&&` chain, no `;`, ends with a distinctive word)

```
cd /home/fpm/public_html && ls -l /tmp/PKG && tar -czf /root/<topic>_before_YYYYMMDD.tar.gz EXISTING_FILES && tar -xzf /tmp/PKG --no-same-owner && chown www-data:www-data FILES && php8.2 -l a.php && php8.2 -l b.php && php8.2 artisan route:clear && php8.2 artisan view:clear && systemctl reload php8.2-fpm && ls -l /root/<topic>_before_YYYYMMDD.tar.gz && echo <TOPIC>-APPLIED
```

- Migration: `&& php8.2 artisan migrate --path=database/migrations/<file>.php --force` before the cache clears (upload the file in the package). Fresh backup first for anything that changes data.
- `config:clear` only when a config file or `.env` changed. Do not run `view:cache` as root.
- If root ran artisan commands that write logs: `chown -R www-data:www-data storage`.
- New folders: `chown -R` them too.
- **Read the pasted output** for `Cannot open`, `No such file`, `Error` before saying it worked.
- Cleanup: the owner deletes the `/tmp` files. `/root/*_before_*` backups stay until the owner says.

## 3. After production

1. `CHANGELOG.md` ("on production", backup name) and a dated entry in `docs/OPERATIONAL_NOTES.md` (package name, sha256, files, backup, what is still open).
2. Commit and `git push origin feature/national-council-session`.
3. Staging (below).

## 4. Staging (done directly)

1. Compare: `git ls-files app config database resources routes public/js public/css composer.json composer.lock`, md5 with CR removed, local against staging over SSH.
2. Tar only the differing files, `scp` to `/tmp`, back up the existing ones to `/root/<topic>_before_YYYYMMDD.tar.gz`, extract, `chown www-data`, `php -l`, `php artisan migrate:status` (run pending ones), `route:clear`, `view:clear`, `systemctl reload php8.1-fpm`, delete the `/tmp` upload.
3. Compare again: 0 differences. For new queries run a read-only smoke script as `www-data` (staging is MySQL 8), then delete it.
4. Staging runs the scheduler by cron; production does **not** (see `OPERATIONAL_NOTES.md`).

## CMS logins and permissions

New page = migration that adds the `pages` row (never only by hand), its own permission line, and `pagePermByUrl()` in routes (page ids differ per server). A menu group needs its own grant on the profile.
