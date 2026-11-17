<?php
/**
 * Page title band with breadcrumb (same markup as the static inner pages).
 *
 * @var array $page
 */
$crumbs = page_breadcrumb($page);
$h1 = (string) ($page['h1'] ?? $page['seo']['title'] ?? '');
?>
    <!-- Hero Start -->
    <div class="container-fluid pb-5 hero-header hero-home">
        <div class="container py-5">
            <div class="hero-glass row g-3 align-items-center p-4 p-lg-5 mx-0">
                <div class="col-lg-6 text-center text-lg-start">
                    <h1 class="display-4 mb-0 animated slideInLeft"><?= e($h1) ?></h1>
                </div>
<?php if (count($crumbs) > 1): ?>
                <div class="col-lg-6 animated slideInRight">
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
