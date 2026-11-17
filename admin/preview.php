<?php
/** Show the saved version of any page (drafts included) to a logged-in editor. Never indexed. */

declare(strict_types=1);

require __DIR__ . '/_init.php';

$page = page_load((string) ($_GET['id'] ?? ''));
if (!$page) {
    http_response_code(404);
    exit('Page not found.');
}
$page['seo']['noindex'] = true;
$html = render_page($page);
$banner = '<div style="position:fixed;bottom:0;left:0;right:0;z-index:99999;background:#1A1208;color:#fff;padding:10px 16px;font:14px/1.4 sans-serif;text-align:center">'
    . 'Preview' . (($page['status'] ?? '') !== 'published' ? ' — this page is a <strong>draft</strong> and not visible on the website' : ' of the saved version')
    . ' · <a href="/admin/edit.php?id=' . e($page['id']) . '" style="color:#E2C97E">Back to the editor</a></div>';
echo str_replace('</body>', $banner . '</body>', $html);
