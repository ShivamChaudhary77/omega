# Blog CMS — Database Setup

These 6 files create **new, independent tables only** — nothing here touches
`contact_form_submissions`, `consultation_leads`, or anything else already in
the database. Every table is prefixed `blog_` and none of it is referenced by
any existing table, so it's safe to skip entirely if you ever want to remove
the CMS later (just `DROP TABLE` the 6 `blog_*` tables — nothing else
depends on them).

## Before you import

Run `SHOW TABLES;` against the production database once, out of caution.
`README-CONTACT-FORM.md` names the database `theomega_wp824` — that suffix
suggests it may be a database that originally ran a real WordPress install
before the site went static (the blog's own content was imported from a
WordPress export — see `../BLOG-README.md`). Nothing found while building
this indicates any live `wp_*` tables still exist, but it's worth a look:
if any `blog_*`-prefixed table already exists for an unrelated reason,
rename yours before importing rather than risk a collision.

## Import order (must run in this order — foreign keys depend on it)

```
001_create_blog_categories.sql
002_create_blog_users.sql
003_create_blog_posts.sql       -- FKs to blog_categories + blog_users
004_create_blog_media.sql       -- FK to blog_users; then ALTERs blog_posts
                                    to add the featured_image_id FK
005_create_blog_revisions.sql   -- FKs to blog_posts + blog_users
006_create_blog_activity_logs.sql -- FK to blog_users
```

Run each file's contents in hPanel → phpMyAdmin → SQL tab (or via the MySQL
CLI if you have shell access), in that exact order, against the same
database `php/config.php` already points at. Do not run them out of order —
`003` references `blog_categories`/`blog_users`, `004`'s final `ALTER TABLE`
references `blog_posts`, `005`/`006` reference `blog_posts`/`blog_users`.

Every `CREATE TABLE` uses `IF NOT EXISTS`, so re-running a file that already
succeeded is harmless (it just does nothing the second time). The one
non-idempotent statement is `004`'s trailing `ALTER TABLE ... ADD CONSTRAINT`
— if you ever need to re-run `004` after it already succeeded once, drop
that one constraint first (`ALTER TABLE blog_posts DROP FOREIGN KEY
fk_blog_posts_featured_image;`) or MySQL will error that it already exists.

## What gets seeded automatically

`001_create_blog_categories.sql` inserts 7 real categories matching the
site's existing taxonomy (Home & Residential Interiors, Kitchen Design,
Cost & Planning Guides, Office & Commercial Interiors, Design Trends &
Inspiration, Interior Design Insights, and a generic "Blog" fallback) so a
new CMS post filed under one of these gets the same `/category-slug/` URL
prefix visitors and Google already know from the existing 79 posts. Two
legacy one-off directories from the original WordPress import
(`builder-floor-interior-design-gurgaon-2026`,
`dlf-the-arbour-interior-design-cost-guide-2026` — each holds exactly one
post, not an ongoing category) are deliberately **not** seeded; don't file
new posts under them.

Nothing else is seeded — no default admin user, no sample posts.

## Creating the first admin user

No SQL file inserts a `blog_users` row with a hardcoded password hash,
deliberately — that would mean a real (even if temporary) password sits in
a `.sql` file that could end up committed to git. Instead, run
`blog-cms/admin/setup.php` once after the tables exist (see
`../DEPLOYMENT.md` step "Create first admin" for the full walkthrough): it
only works when `blog_users` is completely empty, prompts for a name/email/
password on that one visit, hashes the password with `password_hash()`
before it ever touches the database, creates a single `super_admin` row,
and then refuses to run again (checked on every request, not just once) so
it can be left on the server without becoming an open door.

## Column/index notes worth knowing

- `blog_posts.slug` and `blog_media.file_path` are `UNIQUE` — the app layer
  (`includes/blog_functions.php`) checks for a free slug before insert, but
  the DB constraint is the real backstop against a race condition.
- `blog_posts` indexes `status`, `published_at`, `category_id`, `author_id`
  individually (not as one composite index) because the admin blog list
  filters by each of these independently (see `admin/blogs/index.php`) —
  matches how the query actually varies, rather than guessing at a
  multi-column index that only helps one specific filter combination.
- `blog_revisions` and `blog_activity_logs` intentionally have **no** unique
  constraints beyond the primary key — by nature you expect many rows per
  post/user over time.
