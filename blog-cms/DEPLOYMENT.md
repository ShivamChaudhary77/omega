# Blog CMS — Deployment Guide

This module is additive: nothing here touches the existing site's HTML pages, existing tables, or existing PHP endpoints. Follow these steps in order.

## 1. Backup current website
In hPanel → Files → Backups (or File Manager), create a full backup of `public_html` before uploading anything new.

## 2. Backup existing database
hPanel → Databases → phpMyAdmin → Export → the database named in `php/config.php` (referred to as `theomega_wp824` in `README-CONTACT-FORM.md`). Choose "Quick" export, SQL format.

## 3. Upload new CMS files
Upload the entire `blog-cms/` folder, the `database/` folder, and the updated `robots.txt` and `.htaccess` to the site root via File Manager or FTP/SFTP. Nothing else needs to move — every existing file and folder stays exactly where it is.

**Do not skip `.htaccess`** — it now carries the additive rewrite rule that lets the CMS serve new posts (see `database/README.md` and the comment block in `.htaccess` itself). If your host's File Manager hides dotfiles, enable "show hidden files" before uploading, or upload via FTP where dotfiles are visible by default.

## 4. Import SQL files
In phpMyAdmin, run the 6 files in `database/` **in numeric order** (001 through 006) against the same database `php/config.php` points at. Full details, including the "run `SHOW TABLES` first" caution, are in `database/README.md`.

## 5. Configure database connection
Nothing to do here — `blog-cms/includes/db.php` reuses the site's existing `php/config.php` automatically. If that file doesn't exist yet (e.g. a brand-new deployment), copy `php/config.example.php` to `php/config.php` and fill in real credentials first.

## 6. Configure blog URL
Nothing to configure — CMS posts use the exact same `/<category-slug>/<post-slug>/` URL shape as the 79 existing static posts (see `blog-cms/config/blog_config.php`'s `blog_url_note`).

## 7. Configure media directory
`blog-cms/uploads/` must be writable by PHP. On Hostinger this is usually the default for a newly uploaded folder, but confirm permissions are `755` (directories) — see step 8.

## 8. Set correct permissions
- `blog-cms/uploads/` and its subfolders: `755`
- All `.php` files: `644`
- `php/config.php`: `644` (already true if it existed before this change) — never `777`

## 9. Create first admin
Visit `https://theomegagroup.in/blog-cms/admin/setup.php` **once**. This only works while `blog_users` is empty (see `database/README.md`). Fill in a real name, email, and a password of at least 10 characters. After this succeeds, consider deleting or renaming `setup.php` — it refuses to run again on its own, but removing it is one less URL to think about.

## 10. Test admin login
Go to `https://theomegagroup.in/blog-cms/admin/login.php` and log in with the account just created. You should land on the dashboard at `/blog-cms/admin/index.php`.

## 11. Create a draft blog
Blogs → + New Blog. Fill in a title (the slug auto-fills), write some content, choose a category, leave Status as "Draft", and Save.

## 12. Preview the blog
From the blog list or editor, click "Preview" — this opens the post exactly as it will look live, but with a visible "PREVIEW MODE" banner and forced `noindex, nofollow`, and only works while logged in as a user with permission to manage that post.

## 13. Publish the blog
Back in the editor, change Status to "Published" and Save (only Editor/Super Admin roles can do this). Then visit the real URL shown under the Slug field (`https://theomegagroup.in/<category>/<slug>/`) in a new tab/incognito window to confirm it's genuinely public.

## 14. Verify SEO source code
On the published post's live URL, View Source and confirm: a real `<title>`, `<meta name="description">`, `<link rel="canonical">`, OG/Twitter tags, and two `<script type="application/ld+json">` blocks (Article + BreadcrumbList) with real values — not placeholders.

## 15. Verify sitemap
Visit `https://theomegagroup.in/blog-cms/public/sitemap.php` and confirm the new published post's URL appears. This is a separate sitemap from `sitemap.xml`/`sitemap-blog.xml` (which stay exactly as they were, listing only the 79 existing static posts) — `robots.txt` now references all three.

## 16. Verify existing forms
Submit the real contact form (`/contact.html`) and the consultation landing page form (`/free-design-consultation/`) once each, and confirm both still insert rows into `contact_form_submissions` / `consultation_leads` as before. Nothing in this deployment touches either.

## 17. Verify existing pages
Spot-check `index.html`, a few of the 79 existing blog posts, a project page, and a location page (e.g. `/interior-designers-manesar/`) — all should look and function exactly as before, since none of their files changed.

## 18. Verify mobile responsiveness
Check the new post's live page and the admin dashboard on a phone-width viewport. The admin CSS (`blog-cms/assets/css/admin.css`) has its own mobile breakpoint; the public post page reuses the site's existing responsive CSS.

---

## Rolling back

Because every existing file is untouched except `.htaccess` and `robots.txt` (both additive edits), rolling back the CMS entirely is: delete the `blog-cms/` folder, remove the "Blog CMS dynamic routing" block from `.htaccess` (or restore your step-1 backup of it), remove the third `Sitemap:` line and the `Disallow: /blog-cms/admin/` line from `robots.txt`, and drop the 6 `blog_*` tables if you want the database fully clean. The 79 existing posts and every other page are unaffected either way.

## Known limitations (see the implementation report for the full list)
- Scheduled posts go live on the next real visitor request after their scheduled time, not the exact second (no cron dependency — see `blog_functions.php`'s `blog_promote_due_scheduled_posts()`). Upgradeable later to a real Hostinger cron job hitting `blog-cms/public/router.php` or a dedicated cron script on a schedule, if exact-time publishing ever matters.
- New CMS posts do not yet appear in the existing `blog.html` listing page's client-side grid (that page only reads `data/blog-data.json`). They ARE fully live, correct, and indexed at their real URLs, and are included in `blog-cms/public/sitemap.php`. Integrating them into `blog.html`'s listing is a small, separate follow-up (see the implementation report).
