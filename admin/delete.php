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
    $choice = (string) ($_POST['redirect'] ?? 'none');
    $target = $choice === 'custom' ? trim((string) ($_POST['redirect_custom'] ?? '')) : $choice;
    page_delete($id);
    $title = $page['title'] ?? $page['name'] ?? $page['h1'] ?? $page['seo']['title'] ?? $id;
    $message = 'Moved to the trash: ' . $title . '. ';
    if (($page['status'] ?? '') === 'published' && $target !== 'none' && $target !== '') {
        if (!str_starts_with($target, '/') || !path_normalize($target) || $target === $page['urlPath']) {
            $message .= 'The redirect address "' . $target . '" was not valid, so the old address now shows "page not found". Add a redirect under Redirects if needed.';
        } else {
            redirect_set($page['urlPath'], $target);
            $message .= 'Visitors of ' . $page['urlPath'] . ' are now sent to ' . $target . '.';
        }
    } else {
        $message .= 'Its address now shows the "page not found" page.';
    }
    flash('success', $message . ' Restore it from the Trash if this was a mistake.');
}
redirect_to('/admin/');
