<?php
/**
 * Sticky top navigation, built from site.json "nav".
 *
 * @var array $site
 * @var array $page
 */
?>
    <!-- Navbar Start -->
    <div class="container-fluid sticky-top">
        <div class="container">
            <nav class="navbar navbar-expand-lg navbar-light border-bottom border-2 border-white">
                <a href="/index.html" class="navbar-brand">
                    <img class="brand-logo" src="/img/logo.png" alt="<?= e($site['business']['name'] ?? '') ?> logo" width="300" height="42">
                </a>
                <button type="button" class="navbar-toggler ms-auto me-0" data-bs-toggle="collapse"
                    data-bs-target="#navbarCollapse" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarCollapse">
                    <div class="navbar-nav ms-auto">
<?php foreach ((array) ($site['nav'] ?? []) as $item): ?>
                        <a href="<?= e($item['href']) ?>" class="nav-item nav-link<?= nav_is_active($item, $page) ? ' active' : '' ?>"><?= e($item['label']) ?></a>
<?php endforeach; ?>
                    </div>
                </div>
            </nav>
        </div>
    </div>
    <!-- Navbar End -->
