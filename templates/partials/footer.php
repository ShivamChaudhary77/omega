<?php
/**
 * Site footer, built from site.json "footer" and business details.
 *
 * @var array $site
 */
$footer = (array) ($site['footer'] ?? []);
$business = (array) ($site['business'] ?? []);
$name = (string) ($business['name'] ?? '');
$links = function (array $items): void {
    foreach ($items as $item) {
        echo '                    <a class="btn btn-link" href="' . e($item['href']) . '">' . e($item['label']) . "</a>\n";
    }
};
$sameAs = (array) ($business['sameAs'] ?? []);
$facebook = current(array_filter($sameAs, fn ($u) => str_contains($u, 'facebook.com'))) ?: '';
$instagram = current(array_filter($sameAs, fn ($u) => str_contains($u, 'instagram.com'))) ?: '';
$whatsapp = (string) ($site['whatsappNumber'] ?? '');
?>
    <!-- Footer Start -->
    <div class="container-fluid bg-dark text-white-50 footer pt-5">
        <div class="container py-5">
            <div class="row g-5">
                <div class="col-md-6 col-lg-3 wow fadeIn" data-wow-delay="0.1s">
                    <a href="/index.html" class="d-inline-block mb-3 brand-mark">
                        <img class="brand-logo" src="/img/logo.png" alt="<?= e($name) ?> logo" width="300" height="42" loading="lazy">
                    </a>
                    <p class="mb-0"><?= e($footer['about'] ?? '') ?></p>
                </div>
                <div class="col-md-6 col-lg-2 wow fadeIn" data-wow-delay="0.3s">
                    <h5 class="text-white mb-4">Quick Links</h5>
<?php $links((array) ($footer['quickLinks'] ?? [])); ?>
                </div>
                <div class="col-md-6 col-lg-2 wow fadeIn" data-wow-delay="0.4s">
                    <h5 class="text-white mb-4">Service Areas</h5>
<?php $links((array) ($footer['serviceAreas'] ?? [])); ?>
                </div>
                <div class="col-md-6 col-lg-2 wow fadeIn" data-wow-delay="0.5s">
                    <h5 class="text-white mb-4">Our Services</h5>
<?php $links((array) ($footer['services'] ?? [])); ?>
                </div>
                <div class="col-md-6 col-lg-3 wow fadeIn" data-wow-delay="0.7s">
                    <h5 class="text-white mb-4">Get In Touch</h5>
                    <p><i class="fa fa-map-marker-alt me-3"></i><?= e($footer['addressDisplay'] ?? '') ?></p>
                    <p><i class="fa fa-phone-alt me-3"></i><?= e($site['phoneDisplay'] ?? '') ?></p>
                    <p><i class="fa fa-envelope me-3"></i><?= e($business['email'] ?? '') ?></p>
                    <div class="d-flex pt-2">
<?php if ($facebook): ?>
                        <a class="btn btn-outline-primary btn-square border-2 me-2"
                            href="<?= e($facebook) ?>" target="_blank" rel="noopener"
                            aria-label="<?= e($name) ?> on Facebook"><i class="fab fa-facebook-f"></i></a>
<?php endif; ?>
<?php if ($instagram): ?>
                        <a class="btn btn-outline-primary btn-square border-2 me-2"
                            href="<?= e($instagram) ?>" target="_blank" rel="noopener"
                            aria-label="<?= e($name) ?> on Instagram"><i class="fab fa-instagram"></i></a>
<?php endif; ?>
<?php if ($whatsapp): ?>
                        <a class="btn btn-outline-primary btn-square border-2"
                            href="https://wa.me/<?= e($whatsapp) ?>" target="_blank" rel="noopener"
                            aria-label="Chat with <?= e($name) ?> on WhatsApp"><i class="fab fa-whatsapp"></i></a>
<?php endif; ?>
                    </div>
<?php if (!empty($site['mapEmbedUrl'])): ?>
                    <div class="footer-map">
                        <iframe
                            src="<?= e($site['mapEmbedUrl']) ?>"
                            loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                            title="<?= e($name) ?> location map"></iframe>
                    </div>
<?php endif; ?>
                </div>
            </div>
        </div>
        <div class="container wow fadeIn" data-wow-delay="0.1s">
            <div class="copyright">
                <div class="row">
                    <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                        &copy; <a class="border-bottom" href="/index.html"><?= e($name) ?></a>, All Rights Reserved.
                    </div>
                    <div class="col-md-6 text-center text-md-end">
                        <div class="footer-menu">
<?php foreach ((array) ($footer['bottomMenu'] ?? []) as $item): ?>
                            <a href="<?= e($item['href']) ?>"><?= e($item['label']) ?></a>
<?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Footer End -->
