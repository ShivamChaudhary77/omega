<?php
/**
 * Back-to-top button and the JavaScript every page loads. schema.js is not
 * included: the server now renders LocalBusiness, Breadcrumb and FAQ schema
 * directly in the <head>.
 *
 * @var array $head
 */
?>
    <!-- Back to Top -->
    <a href="#" class="btn btn-lg btn-primary btn-lg-square back-to-top" aria-label="Back to top"><i
            class="bi bi-arrow-up"></i></a>


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
