<?php
/**
 * Page title band with breadcrumb (same markup as the static inner pages).
 *
 * @var array       $page
 * @var string|null $h1       heading text (defaults to page h1 / SEO title)
 * @var string|null $variant  "wide" = 7/5 columns + display-5 (location and
 *                            project pages); default = 6/6 + display-4
 */
$crumbs = page_breadcrumb($page);
$h1 = (string) ($h1 ?? $page['h1'] ?? $page['seo']['title'] ?? '');
$wide = ($variant ?? null) === 'wide';
?>
    <!-- Hero Start -->
    <div class="container-fluid pb-5 hero-header hero-home">
        <div class="container py-5">
            <div class="hero-glass row g-3 align-items-center p-4 p-lg-5 mx-0">
                <div class="<?= $wide ? 'col-lg-7' : 'col-lg-6' ?> text-center text-lg-start">
                    <h1 class="<?= $wide ? 'display-5' : 'display-4' ?> mb-0 animated slideInLeft"><?= e($h1) ?></h1>
                </div>
<?php if (count($crumbs) > 1): ?>
                <div class="<?= $wide ? 'col-lg-5' : 'col-lg-6' ?> animated slideInRight">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center justify-content-lg-end mb-0">
<?php foreach ($crumbs as [$name, $path]): ?>
<?php if ($path !== null): ?>
                            <li class="breadcrumb-item"><a class="text-primary" href="<?= e($path) ?>"><?= e($name) ?></a></li>
<?php else: ?>
                            <li class="breadcrumb-item text-secondary active" aria-current="page"><?= e($name) ?></li>
<?php endif; ?>
<?php endforeach; ?>
                        </ol>
                    </nav>
                </div>
<?php endif; ?>
            </div>
        </div>
    </div>
    <!-- Hero End -->
