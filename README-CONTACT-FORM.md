# Contact Form — Backend Setup

The contact form on `contact.html` posts to a PHP endpoint that inserts into a MySQL table. This only works on a host that actually runs PHP (e.g. Hostinger) — it will not work from the local `python3 -m http.server` used elsewhere in this project for static testing, since that server doesn't execute PHP at all. Test it against the real host, or a local PHP dev server (`php -S localhost:8000` from the project root, if PHP is installed locally).

## Files

- **`php/config.php`** — real DB credentials. **Gitignored — never commit this file.** If it's ever accidentally committed, treat the DB password as compromised and rotate it immediately in hPanel → Databases.
- **`php/config.example.php`** — template with the same constants and no real values, safe to commit, documents what `config.php` needs.
- **`php/contact-submit.php`** — the actual endpoint. Validates every field server-side (the client-side validation in `js/contact-form.js` is UX only and can be bypassed), inserts via a PDO prepared statement, and always responds with JSON.
- **`js/contact-form.js`** — intercepts the form submit, does client-side validation, POSTs JSON to `php/contact-submit.php`, shows a success/error message in place, and fires the GA4 `form_submit` key event only once the backend confirms the row was actually saved.

## ⚠️ About the DB password

The database password was pasted directly into this conversation. Depending on how/where this chat is logged, treat that as loosely exposed — **rotating the password in hPanel once the site is confirmed working, then updating `php/config.php` to match, is a reasonable precaution**, not strictly required but cheap insurance.

## Table

```sql
CREATE TABLE contact_form_submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    city VARCHAR(100) NOT NULL,
    project_details TEXT NOT NULL,
    page_url VARCHAR(500) NOT NULL,
    referrer_url VARCHAR(500) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_phone_number (phone_number),
    INDEX idx_submitted_at (submitted_at)
);
```

This wasn't created by this change — run it yourself in hPanel → phpMyAdmin (or your MySQL client of choice) against the `theomega_wp824` database if it doesn't already exist.

## Field mapping

| Form field | DB column | Notes |
|---|---|---|
| Full Name | `full_name` | required |
| Phone Number | `phone_number` | required, loosely validated (`^[0-9+\-\s()]{7,20}$`) |
| City | `city` | required |
| Tell us about your project | `project_details` | required |
| — | `page_url` | sent by the client as `window.location.href`; falls back to the `Referer` HTTP header server-side if missing |
| — | `referrer_url` | `document.referrer` — where the visitor was before landing on the page with the form (empty/direct if typed or bookmarked) |
| — | `ip_address` | server-side only, from `$_SERVER['REMOTE_ADDR']` — never trust a client-supplied IP |
| — | `user_agent` | server-side only, from `$_SERVER['HTTP_USER_AGENT']` |

Note there's no `email` field — the table has none, and the current spec is Full Name / Phone / City / Project only. If email is needed later, add an `email` column to the table and a matching field in both `contact.html` and `contact-submit.php`.

## Security notes

- **SQL injection**: not possible — the insert uses a PDO prepared statement with bound parameters, never string-concatenated SQL.
- **Spam**: a honeypot field (`website`, hidden via CSS, `tabindex="-1"`) catches basic bots that fill every input. Submissions with that field non-empty are silently reported as "success" (so the bot doesn't retry) without touching the DB. There's no rate-limiting beyond that — worth adding if spam becomes a real problem (e.g. cap submissions per IP per hour, or add a CAPTCHA).
- **CORS**: intentionally not enabled. The endpoint only accepts same-origin requests (the default browser behavior); no `Access-Control-Allow-Origin` header is sent, so another site's JavaScript can't POST to it.
- **Error handling**: DB errors are logged server-side via `error_log()` and never echoed to the client — the client only ever sees a generic "something went wrong, call or WhatsApp us" message.

## Testing without a live PHP host

I wrote and reviewed `php/contact-submit.php` carefully, but **could not execute it** — there's no PHP runtime or network path to the Hostinger MySQL instance from this environment, so the DB connection itself is unverified. Before relying on it in production:

1. Deploy `php/` (with a real `config.php`, copied from `config.example.php`) to the host.
2. Confirm the `contact_form_submissions` table exists (SQL above).
3. Submit the form on the live site and check the row lands in the table.
4. Check `error_log` (hPanel → Advanced → PHP error logs, or wherever your host routes it) if it doesn't.
