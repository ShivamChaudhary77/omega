<?php
/**
 * JavaScript at the end of <body>. Pages may replace the whole list with
 * "assets.scripts" (the ads landing pages load a lighter set). schema.js is
 * never included: the server renders all structured data in the <head>.
 *
 * @var array $head
 * @var bool  $backToTop  show the back-to-top button (default true)
 */
$backToTop = $backToTop ?? true;
?>
<?php if ($backToTop): ?>
    <!-- Back to Top -->
    <a href="#" class="btn btn-lg btn-primary btn-lg-square back-to-top" aria-label="Back to top"><i
            class="bi bi-arrow-up"></i></a>

<?php endif; ?>

<?php if ($head['scripts'] !== null): ?>
    <!-- JavaScript (page-specific list) -->
<?php foreach ($head['scripts'] as $js): ?>
    <script src="<?= e($js) ?>"></script>
<?php endforeach; ?>
<?php else: ?>
    <!-- JavaScript Libraries -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/lib/wow/wow.min.js"></script>
    <script src="/lib/easing/easing.min.js"></script>
    <script src="/lib/waypoints/waypoints.min.js"></script>
<?php if ($head['owlCarousel']): ?>
    <script src="/lib/owlcarousel/owl.carousel.min.js"></script>
<?php endif; ?>

    <!-- Template Javascript -->
    <script src="/js/main.js"></script>
    <script src="/js/analytics.js"></script>
<?php foreach ($head['extraJs'] as $js): ?>
    <script src="<?= e($js) ?>"></script>
<?php endforeach; ?>
<?php endif; ?>
