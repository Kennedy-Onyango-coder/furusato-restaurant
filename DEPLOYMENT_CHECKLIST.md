# Production Deployment Checklist

Run through this list for every production deployment. The GitHub Actions
workflow automates the bolded items; the rest are situational.

## Before pushing

- [ ] `git status` clean of unintended files (`git status`)
- [ ] No `data/*.json` (except `*.example.json`), logs, backups, `.env*`,
      `includes/.env.php`, or admin credentials in the commit
      (`git ls-files data/ logs/`)
- [ ] Changes reviewed (diff read, no debug/localhost leftovers)
- [ ] `bash .github/scripts/php-lint.sh` — **(automated)**
- [ ] `bash .github/scripts/secret-scan.sh` — **(automated)** old WhatsApp key,
      hardcoded credentials, private keys, secret files
- [ ] `bash .github/scripts/deploy-safety.sh` — **(automated)** deploy payload
      contains no production data/uploads

## Workflow gates (all automated, in order)

- [ ] **Validation job passes** — lint + secret scan + safety check
- [ ] **Production backup created** — `~/backups/furusato/furusato-production-<timestamp>.tar.gz`
      on the server, before any file changes
- [ ] **Dry-run reviewed** — rsync dry-run output shows only intended code files
- [ ] **Deploy completed** — code-only rsync (no `--delete`)

## After deployment (automated + verify)

- [ ] **Website responds** — `/`, `menu.php`, `our-story.php`, `contact.php`,
      `sitemap.php` return 2xx/3xx
- [ ] **Admin login reachable** — `/admin/login.php` (no credential check)
- [ ] **Assets served** — `assets/css/style.css` loads (styling intact)
- [ ] **Admin data preserved** — `data/settings.json`, `menu.json`, `hero.json`,
      `specials.json`, `admin.json` still present on the server
- [ ] **Images preserved** — `assets/images/menu|hero|gallery` still populated
- [ ] **Permissions sane** — runtime dirs writable, nothing 777
- [ ] **Logs checked** — no new PHP fatal errors in `logs/`
- [ ] **Service worker bumped** — `sw.js` cache name updated when cached
      assets changed (e.g. `furusato-v5` → `v6`)

## If anything failed

- [ ] Validation failed → nothing was touched; fix and re-push
- [ ] Backup failed → nothing was touched; check `~/backups` permissions/size
- [ ] Deploy failed mid-way → code may be partially updated; **data/uploads are
      still safe** (never deleted). Fix and re-push, or roll back code (below)
- [ ] Site broken but you need the old code → `git revert <commit>` + push
- [ ] Production data actually corrupted → restore only the affected file from
      `~/backups/furusato/furusato-production-<timestamp>.tar.gz` (see
      DEPLOYMENT.md §8) — never restore blindly
- [ ] Rollback backup identified → backup timestamp matches the workflow run
      time immediately before the bad deploy

## First deployment only (extra steps)

- [ ] GitHub secrets added: `HOSTINGER_HOST`, `HOSTINGER_PORT`, `HOSTINGER_USER`,
      `HOSTINGER_SSH_KEY`, `HOSTINGER_DEPLOY_PATH`
- [ ] GitHub variable set: `DEPLOY_ENABLED=true`
- [ ] `includes/.env.php` created on the server with rotated WhatsApp key + SMTP
      values, `chmod 600`
- [ ] Old CallMeBot key revoked/rotated (old key was public in Git history)
- [ ] Manual rsync dry-run reviewed (DEPLOYMENT.md §13)
- [ ] First automated run watched end-to-end in the Actions tab
- [ ] Post-deploy: spot-check menu items, image uploads, reservation email
