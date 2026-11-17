# Go-live runbook: switching theomegagroup.in to the dynamic site

**Time needed:** about 30 minutes.
**Downtime:** none. The switch is one command, and undoing it is one command.
**Rehearsed:** the full sequence below was run on simulated copies of the server, for both deploy styles. All 148 pages, the generated files, redirects, 404s, private files and the dashboard passed (187 checks).

## Before you start

1. **Pick a quiet time,** and tell anyone who publishes blog posts the old way (editing `data/blog-data.json` and running the Python scripts) to **stop**. From go-live on, posts are written in the dashboard.
2. **Back up the whole site in cPanel** (Files → Backup → download a home directory backup). The go-live script also backs up everything it removes, but a full backup is cheap insurance.
3. **Check the PHP version:** cPanel → Software → **Select PHP Version** (or MultiPHP Manager) must show **PHP 8.0 or newer** for theomegagroup.in. Under **Extensions**, these must be ticked:
   - `dom`
   - `gd`
   - `fileinfo`
   - `mbstring`
4. **Note how cPanel deploys:** cPanel → **Git Version Control** → Manage → note the **Repository Path**.
   - **Path is `/home/theomega/public_html` (the website folder itself):** the repository is checked out straight into the website. This is likely, because files the deploy config excludes, like `content/` and `.cpanel.yml`, are on the server today.
   - **Any other path:** the repository lives there and "Deploy HEAD Commit" copies it into `public_html`.

   Both work: you'll run the script from the Repository Path.

## Step 1: deploy the new code (nothing changes for visitors)

Your developer merges and pushes "Go live (step A)" to GitHub `main`. Then in cPanel → Git Version Control → Manage → **Pull or Deploy**:
1. Click **Update from Remote**.
2. Click **Deploy HEAD Commit**.

**Check:** the website looks exactly as before, and `https://theomegagroup.in/admin/` says *"The dashboard is not set up on this server yet."*

## Step 2: switch on (cPanel → Terminal)

Open cPanel → **Terminal**, then:

```bash
cd /home/theomega/public_html
```

Use your Repository Path from "Before you start" in place of `/home/theomega/public_html` if it's different. Then run a **dry run**. It checks everything and changes nothing:

```bash
bash scripts/go-live.sh
```

**If it says the PHP version is too old:** the Terminal's `php` can be a different version from the website's. Point it at the right one and run the dry run again:

```bash
PHP=/opt/cpanel/ea-php82/root/usr/bin/php bash scripts/go-live.sh
```

Use the version the site runs, e.g. `ea-php81`. When the dry run ends with *"everything is ready"*, switch over:

```bash
bash scripts/go-live.sh --apply
```

It asks for the **dashboard password** twice. Choose a strong one (at least 10 characters) and save it in a password manager. The script then:
1. backs up the static pages
2. creates the content folder `/home/theomega/omega-content`
3. switches the site on
4. removes the old static pages
5. checks the live site

It ends with **"Done: the dynamic site is live."** and prints the exact **undo command**. Copy that undo command somewhere safe.

**No Terminal in your cPanel?** Use **Cron Jobs** instead (next section).

### Step 2 without Terminal: cPanel → Cron Jobs

Cron Jobs runs a command on the server for you. The script notices it has no terminal, so it doesn't ask for a password. It writes a **private one-time link** into a log file instead, and you use that link to set the password in your browser.

**2a. Dry run (checks only, changes nothing)**
1. Open cPanel → **Cron Jobs**. Under **Add New Cron Job**, set **Common Settings** to **Once Per Minute**, and paste this **Command**:

   ```bash
   bash $HOME/public_html/scripts/go-live.sh > $HOME/go-live-dryrun.txt 2>&1
   ```

2. Click **Add New Cron Job**, wait 2 minutes, then **delete** that cron job (the bin icon in the list below).
3. Open cPanel → **File Manager**. In your **home folder** (the one that contains `public_html`, not inside it), open `go-live-dryrun.txt`. It should end with *"Dry run finished: everything is ready."* If it shows ✗ lines instead, send them to your developer.

**2b. Switch on**
1. Add a new cron job, again **Once Per Minute**, with this command:

   ```bash
   [ -e "$HOME/omega-content" ] || bash $HOME/public_html/scripts/go-live.sh --apply > $HOME/go-live-log.txt 2>&1
   ```

   The part before `||` makes sure it can only ever run once.
2. Wait 2 minutes, then **delete** the cron job.
3. In File Manager, open `go-live-log.txt` (home folder). Near the end it says **"Done: the dynamic site is live."**
4. It also contains a line like `https://theomegagroup.in/admin/setup.php?token=…`. Open that link in your browser and choose the dashboard password (at least 10 characters; save it in a password manager). The link stops working once the password is set.
5. Keep `go-live-log.txt`: it also contains the **undo command**. If you ever need to undo without Terminal, run that command the same way, as a once-only cron job.

## Step 3: remove the static pages from the repository

Your developer merges and pushes "Go live (step B)". In cPanel, click **Update from Remote** and then **Deploy HEAD Commit** again. This stops future deploys from bringing old pages back.

## Step 4: daily backups (cPanel → Cron Jobs)

Add a cron job that runs **once per day** (e.g. 3:15 am), with this command:

```bash
bash /home/theomega/public_html/scripts/backup-content.sh
```

Use your Repository Path if it's different. It keeps the last 14 days of content and uploaded images in `/home/theomega/omega-content-backups/`.

## Step 5: checks

- **Developer:** runs `php tests/verify-live.php https://theomegagroup.in`. It checks every page, the sitemaps, the redirects, the 404 page and that private files are hidden.
- **You:** log in at `https://theomegagroup.in/admin/` and change one small thing (for example a word in an FAQ answer), save, and look at the page.
- **Google Search Console:**
  - Sitemaps: re-submit `sitemap.xml` and `sitemap-blog.xml`.
  - Remove `blog-cms/public/sitemap.php` if it's listed.
  - Watch **Pages** (indexing) for two weeks.
- **GA4:** check that visits and key events keep arriving (the tags are on every page).

## If something is wrong: undo

Run the undo command that go-live printed, for example:

```bash
bash /home/theomega/public_html/scripts/rollback-go-live.sh --backup /home/theomega/omega-golive-backup-YYYYMMDD-HHMMSS.tar.gz --apply
```

It puts every static page back and switches the dynamic site off, so the old site is served again within seconds. The content folder (and any dashboard edits) is kept, so you can switch on again later by running Step 2 after moving `/home/theomega/omega-content` aside.

## What changes after go-live

- **Content is edited only in the dashboard** (`DASHBOARD-GUIDE.md`). It lives in `/home/theomega/omega-content`, outside the website folder, so deploys never overwrite it.
- **Developers still deploy code (templates, styles, features) through GitHub and cPanel as before.** The repository's `content/` folder is now just the snapshot taken at go-live. The live content is on the server, in the daily backups.
- **Retired:** the Python page generators (`scripts/build-*.py`) and `data/blog-data.json`. Don't run them; they would recreate static pages that hide the dashboard's versions.
- **Fixed at go-live:** two problems on today's site.
  - Unknown addresses like `/project/anything/` now give a proper "page not found" instead of a server error (HTTP 500).
  - Internal documents (`*.md`, `.cpanel.yml`) are no longer publicly readable.
