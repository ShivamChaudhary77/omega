<?php
/** Move a page to the trash (POST + CSRF). The home page can't be deleted. */

declare(strict_types=1);

require __DIR__ . '/_init.php';
require_post_with_csrf();

$id = (string) ($_POST['id'] ?? '');
$page = page_load($id);
if (!$page) {
    flash('danger', 'That page does not exist.');
} elseif ($page['type'] === 'home') {
    flash('danger', 'The home page cannot be deleted.');
} else {
    page_delete($id);
    flash('success', 'Moved to the trash: ' . ($page['title'] ?? $page['name'] ?? $page['h1'] ?? $page['seo']['title'] ?? $id)
        . '. Its address now shows the "page not found" page. Restore it from the Trash if this was a mistake.');
}
redirect_to('/admin/');
