<?php
/**
 * Outer shell shared by every page.
 *
 * @var array  $site
 * @var array  $page
 * @var array  $head     from page_head()
 * @var string $content  the page body rendered by its type template
 */
$showCta = ($page['cta'] ?? true) !== false;
?>
<!DOCTYPE html>
<html lang="en">

<?php partial('head', ['head' => $head, 'site' => $site]); ?>

<body>
    <!-- Spinner Start -->
    <div id="spinner"
        class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
        <div class="spinner-grow text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="sr-only">Loading...</span>
        </div>
    </div>
    <!-- Spinner End -->


<?php partial('nav', ['site' => $site, 'page' => $page]); ?>

<?= $content ?>

<?php if ($showCta) { partial('cta', ['site' => $site, 'page' => $page]); } ?>

<?php partial('footer', ['site' => $site]); ?>

<?php partial('scripts', ['head' => $head]); ?>
</body>

</html>
