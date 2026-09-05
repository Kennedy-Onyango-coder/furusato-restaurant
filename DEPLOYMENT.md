# Furusato Restaurant — Deployment Guide

This document explains how code gets from development to
`https://furusatorestaurant.com` **without ever touching production data,
reservations, settings, admin accounts, backups or uploaded media**.

---

## 1. Architecture (two sources of truth)

```
Devin / Cline / Developer
        |
        | git push (main)
        v
GitHub  Actions  ── validate: PHP lint · secret scan · deploy-safety check
        |         ── backup:  tar of data/ + uploads on the server (~/backups)
        |         ── deploy:  rsync CODE ONLY (data/, logs/, uploads excluded)
        |         ── verify:  files, permissions, live URLs
        v
Hostinger (furusatorestaurant.com)
```

| Layer | Source of truth | Lives in |
|---|---|---|
| Application code (PHP, CSS, JS, templates) | **GitHub** | this repository |
| Production state (menu data, settings, reservations, admin accounts, audit logs, uploaded images, runtime logs, backups, secrets) | **Hostinger** | never committed |

The deployment payload is **code only**. It can add/update source files; it can
never delete or overwrite production state.

---

## 2. What deployments never touch (protected on the server)

| Path | Why protected |
|---|---|
| `data/` | JSON "database": settings, menu, hero, specials, admin, reservations, audit, rate limits, `data/backups/` |
| `logs/` | Runtime logs |
| `assets/images/menu/`, `assets/images/gallery/` | Admin-uploaded media — the **server** is the source of truth |
| `assets/images/hero/` (admin uploads only) | Admin-uploaded media — the **server** is the source of truth. Static repo-controlled hero assets (`out-furusato.webp`, `sushi-hero.webp`, …) **do deploy**; only admin-generated filenames (`<name>_<16hex>.webp` from `convertToWebP()`) are excluded. rsync has no `--delete`, so server-only files can never be removed by a deploy. |
| `includes/.env.php` | Production secrets — exists **only** on the server, never in Git |

These are enforced in **two independent places**:

1. `.github/scripts/rsync-exclude.txt` — rsync never transfers them.
2. `.github/scripts/deploy-safety.sh` — fails the build if any of them are
   tracked in git, so a bad commit can't even reach the deploy step.

There is intentionally **no `--delete`** on rsync: a deployment can never remove
a production file that is absent from GitHub.

---

## 3. Required GitHub configuration (one-time manual setup)

### 3.1 Repository **Variables** (Settings → Secrets and variables → Actions → Variables)

| Name | Value |
|---|---|
| `DEPLOY_ENABLED` | `true` to turn on automatic deployment (leave unset/false until SSH is configured — pushes stay green but skip deploying) |

### 3.2 Repository **Secrets** (Settings → Secrets and variables → Actions → Secrets)

| Secret | How to obtain |
|---|---|
| `HOSTINGER_HOST` | Hostinger panel → hosting plan → **SSH Access** — usually the domain, e.g. `furusatorestaurant.com` |
| `HOSTINGER_PORT` | Usually `65002` for Hostinger (shown on the same SSH Access page) |
| `HOSTINGER_USER` | The SSH username shown on the SSH Access page (e.g. `uXXXXXX`) |
| `HOSTINGER_SSH_KEY` | The **private** key generated below (whole file, incl. header/footer) |
| `HOSTINGER_DEPLOY_PATH` | Absolute path to the web root, e.g. `/home/uXXXXXX/domains/furusatorestaurant.com/public_html`. Find via SSH (`cd ~/domains/furusatorestaurant.com/public_html && pwd`) or the hPanel File Manager address bar. |

### 3.3 SSH key setup

```bash
# on your machine
ssh-keygen -t ed25519 -C "furusato-deploy" -f furusato_deploy_key -N ""
# add the PUBLIC key on Hostinger:
#   hPanel → SSH Access → Manage SSH keys → Add key (paste furusato_deploy_key.pub)
#   or append it to ~/.ssh/authorized_keys on the server
# paste the PRIVATE key (furusato_deploy_key) into the HOSTINGER_SSH_KEY secret
```

Test before enabling:

```bash
ssh -i furusato_deploy_key -p 65002 uXXXXXX@furusatorestaurant.com "echo ok && pwd"
```

### 3.4 Server prerequisites

- Confirm `rsync` and `tar` exist on the server (Hostinger shared hosting has both).
- `~/backups/furusato/` is created automatically by the backup step.

---

## 4. Production secrets on the server (`includes/.env.php`)

Create **once, on the server only** (never in Git, never deployed):

```bash
cd ~/domains/furusatorestaurant.com/public_html
nano includes/.env.php
```

```php
<?php
// PRODUCTION SECRETS - exists only on the server, gitignored, never deployed.
return [
    'WHATSAPP_API_KEY' => 'NEW_ROTATED_KEY',   // rotated CallMeBot key
    'WHATSAPP_PHONE'   => '254734639203',
    'SMTP_HOST'        => 'smtp.hostinger.com',
    'SMTP_PORT'        => '465',
    'SMTP_SECURE'      => 'ssl',               // ssl | tls | none
    'SMTP_USER'        => 'reservations@furusatorestaurant.com',
    'SMTP_PASS'        => 'MAILBOX_PASSWORD',
    'SMTP_FROM'        => 'reservations@furusatorestaurant.com',
    'SMTP_FROM_NAME'   => 'Furusato Japanese Restaurant',
    'APP_ENV'          => 'production',
    'APP_URL'          => 'https://furusatorestaurant.com',
];
```

Restrict it: `chmod 600 includes/.env.php`

How the app resolves configuration (single central mechanism,
`includes/config.php`): **real environment variables → `includes/.env.php` →
admin-managed values in `data/settings.json`** (WhatsApp key/phone only).
Nothing secret is ever rendered to HTML, JS, API responses or logs — the
admin dashboard shows an empty key field with a "saved on server" placeholder.

> SMTP: while SMTP values are unset, reservation emails silently continue via
> PHP `mail()` exactly as before. Once SMTP is configured, mail is sent
> authenticated from a Furusato-owned address with the customer only in
> `Reply-To`. Send a test from the admin dashboard (leave the key blank to test
> the stored one).

---

## 5. Normal workflow (day to day)

```text
Edit in Devin/Cline
        ↓ review the diff
git add .
git commit -m "..."
git push origin main
        ↓
GitHub Actions runs automatically:
   validate  →  backup  →  deploy (code only)  →  verify
        ↓
Live site updated; data/uploads/logs untouched
```

Manual trigger: **Actions → Deploy to Hostinger (production) → Run workflow**.
The Hostinger File Manager is no longer part of normal deployments.

---

## 6. Emergency: disable automatic deployment

- Set the repository variable `DEPLOY_ENABLED` to `false` (or delete it).
  Pushes keep passing validation but nothing is deployed.
- To freeze the pipeline entirely: Settings → Actions → disable workflows, or
  remove the `HOSTINGER_SSH_KEY` secret.

---

## 7. Inspecting failed deployments

1. Open the failing run in the **Actions** tab — the failing step names itself
   (`Validate…`, `Backup…`, `Deploy…`, `Post-deployment verification…`).
2. Validation failure = nothing was touched on the server. Fix and re-push.
3. Backup step failure = nothing was touched (backup runs before deploy).
4. Deploy step failure mid-way = the pre-deploy backup exists; see §8.
5. Verification failure = code landed but checks failed; server state is intact
   (deploys never delete), fix forward with another push.

No secret values are ever printed by any step.

---

## 8. Rollback procedure

Every deployment creates, **before changing anything**:

```
~/backups/furusato/furusato-production-YYYY-MM-DD-HHMMSS.tar.gz   (chmod 600)
```

containing `data/`, `logs/`, and the three upload folders, stored **outside
`public_html`**. The 14 newest backups are kept.

### Roll back application code
```bash
git log --oneline           # find the last known-good commit
git revert <bad-commit>     # or: git checkout <good-commit> -- path/
git push origin main        # redeploys the reverted code automatically
```

### Restore production JSON (only if actually corrupted)
```bash
cd ~/backups/furusato
tar -tzf furusato-production-YYYY-MM-DD-HHMMSS.tar.gz      # inspect first
mkdir restore && tar -xzf furusato-production-*.tar.gz -C restore
cp restore/data/menu.json ~/domains/furusatorestaurant.com/public_html/data/
```
Never restore blindly; compare with the live file first.

### Restore uploaded media (only if actually lost)
```bash
tar -xzf ~/backups/furusato/furusato-production-*.tar.gz -C /tmp/restore
cp -a /tmp/restore/assets/images/menu/* \
      ~/domains/furusatorestaurant.com/public_html/assets/images/menu/
```

### Identify which backup belongs to a deployment
The backup timestamp equals the time the workflow's **Backup** step ran — check
the run's start time in the Actions tab; the immediately preceding backup is the
matching restore point.

---

## 9. Branch protection (recommended, GitHub UI)

Settings → Branches → Add rule for `main`:
- ✅ Require status checks: **Validate (lint, secrets, safety)**
- ✅ Require a pull request before merging (optional for a solo developer)
- ✅ Do not allow force pushes
- ✅ Do not allow deletions

Deployments only ever trigger from `main`.

---

## 10. Secret rotation

| What | How |
|---|---|
| CallMeBot WhatsApp key | Get a new key from CallMeBot, put it in `includes/.env.php` (`WHATSAPP_API_KEY`) and/or save it in the admin dashboard, then revoke the old key. The old key was publicly exposed in Git history and must be treated as compromised. |
| SMTP password | Change the mailbox password in hPanel → Emails, update `SMTP_PASS` in `includes/.env.php`. |
| SSH deploy key | Generate a new keypair, replace `HOSTINGER_SSH_KEY` + the pubkey on the server, remove the old `authorized_keys` line. |
| Admin password | Admin dashboard → change password (never stored in Git). |

After rotating, run `bash .github/scripts/secret-scan.sh` — it fails if the old
key ever returns.

---

## 11. Adding a new developer

1. Add them as a collaborator (Settings → Collaborators) or use a fork + PRs.
2. They never need production secrets: `includes/.env.php` and all production
   JSON live only on the server.
3. Clone and run locally against the committed `data/*.example.json` templates.
4. Their pushes to `main` deploy only after the same validation gates.

---

## 12. Git history cleanup (required once)

The compromised key exists in reachable history (commits `629b3d4`, `c1d8d69`,
`1413505`). Current tracked files are clean; history rewrite is **recommended
but optional** because the key is being rotated anyway. If you choose to purge:

```powershell
# recovery point first!
git branch backup-before-purge
git push origin backup-before-purge

# using git-filter-repo (pip install git-filter-repo)
git filter-repo --replace-text expressions.txt   # line: <old-leaked-key>==>REDACTED
git push --force origin main
```

⚠️ Force-pushing rewrites history — collaborators must re-clone, and old PR
caches on GitHub may still surface the string until GitHub support purges them.
Since the key is rotated and dead, a rewrite is cosmetic, not urgent.

---

## 13. Local dry-run of the deployment payload

Before the first real deploy, review exactly what rsync would send:

```bash
rsync -azc --dry-run --itemize-changes \
  --exclude-from=.github/scripts/rsync-exclude.txt \
  ./ user@host:/path/to/public_html/
```

Expected: **zero** lines mentioning `data/`, `logs/`,
`assets/images/menu|gallery/`, `.git/`, `.github/`. The `assets/images/hero/`
directory is shared: static repo assets deploy, while admin-generated
`<name>_<16hex>.webp` uploads are excluded (see `rsync-exclude.txt`).

---

## 14. Future architecture note (do NOT implement now)

Current: PHP + flat JSON persistence. A future project could migrate
menu/reservations/settings to MySQL/MariaDB with server-side media storage.
That migration is deliberately out of scope here and must not be mixed into
deployment work.


