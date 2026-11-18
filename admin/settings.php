<?php
/** Site-wide settings (site.json) and the dashboard password. */

declare(strict_types=1);

require __DIR__ . '/_init.php';
require __DIR__ . '/_form.php';

$site = site();
$errors = [];

$fields = [
    'Business details' => [
        f('business.name', 'Business name', 'text', ['required' => true]),
        f('business.alternateName', 'Other business name (Google Business Profile)'),
        f('phoneDisplay', 'Phone number as shown on the site', 'text', ['placeholder' => '+91 981 1001 900']),
        f('business.telephone', 'Phone number for search engines', 'text', ['placeholder' => '+91-981-1001-900']),
        f('whatsappNumber', 'WhatsApp number (digits only, with country code)', 'text', ['placeholder' => '919811001900']),
        f('business.email', 'Email'),
        f('business.address.streetAddress', 'Street address'),
        f('business.address.addressLocality', 'City'),
        f('business.address.addressRegion', 'State'),
        f('business.address.postalCode', 'PIN code'),
        f('footer.addressDisplay', 'Address as shown in the footer', 'textarea', ['rows' => 2]),
        f('business.areaServed', 'Areas served (one per line)', 'lines', ['rows' => 5]),
        f('business.sameAs', 'Social media profile links (one per line)', 'lines', ['rows' => 3]),
        f('googleBusinessProfileUrl', 'Google Business Profile link'),
    ],
    'Key figures' => [
        f('stats.yearsExperience', 'Years of experience', 'text', ['placeholder' => '10+']),
        f('stats.projectsDelivered', 'Projects delivered', 'text', ['placeholder' => '90+']),
        f('stats.onTimeDeliveryRate', 'On-time delivery', 'text', ['placeholder' => '90%']),
        f('stats.qcChecksPerProject', 'Quality checks per project', 'text', ['placeholder' => '70+']),
        f('startingPrice.display', 'Starting price', 'text', ['placeholder' => '₹15 Lakhs+']),
    ],
    'Menu and footer' => [
        f('nav', 'Top menu', 'repeater', ['fields' => [f('label', 'Label'), f('href', 'Link'), f('key', 'Key (internal, keep as is)')], 'itemLabel' => 'Menu item']),
        f('footer.about', 'Footer description', 'textarea', ['rows' => 3]),
        f('footer.serviceAreas', 'Footer: service areas', 'repeater', ['fields' => [f('label', 'Label'), f('href', 'Link')], 'itemLabel' => 'Link']),
    ],
    'Consultation box (default wording)' => [
        f('cta.eyebrow', 'Small heading'),
        f('cta.heading', 'Heading'),
        f('cta.text', 'Text', 'textarea', ['rows' => 2]),
        f('cta.buttonLabel', 'Button text'),
        f('cta.buttonHref', 'Button link'),
        f('cta.image', 'Image', 'image'),
        f('cta.imageAlt', 'Image description'),
    ],
    'Tracking codes' => [
        f('tracking.ga4MeasurementId', 'Google Analytics 4 ID', 'text', ['placeholder' => 'G-XXXXXXXXXX']),
        f('tracking.metaPixelId', 'Meta Pixel ID'),
        f('tracking.googleSiteVerification', 'Google Search Console verification code'),
    ],
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post_with_csrf();
    if (($_POST['action'] ?? '') === 'password') {
        $current = (string) ($_POST['current'] ?? '');
        $new = (string) ($_POST['new'] ?? '');
        if (!admin_password_verify($current)) {
            $errors['password'] = 'The current password is wrong.';
        } elseif ($new !== (string) ($_POST['confirm'] ?? '')) {
            $errors['password'] = 'The two new passwords do not match.';
        } else {
            try {
                admin_password_set($new);
                session_regenerate_id(true);
                flash('success', 'Password changed.');
                redirect_to('/admin/settings.php');
            } catch (InvalidArgumentException $e) {
                $errors['password'] = $e->getMessage();
            }
        }
    } else {
        $form = (array) ($_POST['f'] ?? []);
        $new = $site;
        foreach ($fields as $group) {
            foreach ($group as $field) {
                arr_set($new, $field['key'], field_value($field, arr_get($form, $field['key'])));
            }
        }
        foreach ($fields as $group) {
            foreach ($group as $field) {
                if (!empty($field['required']) && arr_get($new, $field['key']) === null) {
                    $errors[$field['key']] = $field['label'] . ' is required.';
                }
            }
        }
        if (!$errors) {
            site_save($new);
            flash('success', 'Settings saved. Every page now uses them.');
            redirect_to('/admin/settings.php');
        }
        $site = $new;
    }
}

admin_header('Settings', 'settings');
$scripts = [];
?>
        <div class="admin-pagehead"><h1>Settings</h1></div>
<?php if ($errors): ?>
        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
        <p class="admin-lead mb-3">Details used across the whole website. Change them once here and every page updates.</p>
        <form method="post" class="admin-settings" data-tabs>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="settings">
<?php foreach ($fields as $title => $group): ?>
            <section class="card admin-card" data-tab-title="<?= e($title) ?>" data-tab-icon="<?= e(['Business details' => 'bi-shop', 'Key figures' => 'bi-bar-chart', 'Menu and footer' => 'bi-menu-button-wide', 'Consultation box (default wording)' => 'bi-chat-square-text', 'Tracking codes' => 'bi-graph-up'][$title] ?? 'bi-card-text') ?>">
                <h2 class="admin-card-title"><?= e($title) ?></h2>
<?php foreach ($group as $field) { render_field($field, arr_get($site, $field['key']), field_name($field['key']), $errors); } ?>
            </section>
<?php endforeach; ?>
            <button class="btn btn-dark" type="submit"><i class="bi bi-check2"></i> Save settings</button>
        </form>

        <section class="card admin-card mt-5" id="password">
            <h2 class="admin-card-title">Change the dashboard password</h2>
<?php if (isset($errors['password'])): ?>
            <div class="alert alert-danger"><?= e($errors['password']) ?></div>
<?php endif; ?>
            <form method="post" autocomplete="off" style="max-width:420px">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="password">
                <label class="form-label" for="pw_current">Current password</label>
                <input class="form-control mb-2" type="password" id="pw_current" name="current" required autocomplete="current-password">
                <label class="form-label" for="pw_new">New password (at least <?= PASSWORD_MIN_LENGTH ?> characters)</label>
                <input class="form-control mb-2" type="password" id="pw_new" name="new" minlength="<?= PASSWORD_MIN_LENGTH ?>" required autocomplete="new-password">
                <label class="form-label" for="pw_confirm">Repeat the new password</label>
                <input class="form-control mb-3" type="password" id="pw_confirm" name="confirm" minlength="<?= PASSWORD_MIN_LENGTH ?>" required autocomplete="new-password">
                <button class="btn btn-outline-dark" type="submit">Change password</button>
            </form>
        </section>

        <div class="modal fade" id="imagePicker" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
                <div class="modal-header"><h2 class="modal-title h5">Choose an image</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div class="d-flex gap-2 mb-3 flex-wrap">
                        <input type="search" class="form-control" style="max-width:320px" placeholder="Search by file name…" data-picker-search>
                        <label class="btn btn-dark mb-0"><i class="bi bi-upload"></i> Upload new<input type="file" accept="image/jpeg,image/png,image/webp" hidden data-picker-upload multiple></label>
                        <span class="small text-muted align-self-center" data-picker-status></span>
                    </div>
                    <div class="picker-grid" data-picker-grid></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-dark" data-picker-done hidden>Add selected</button></div>
            </div></div>
        </div>
<?php
admin_footer($scripts);
