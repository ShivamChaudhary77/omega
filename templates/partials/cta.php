<?php
/**
 * "Free Luxury Design Consultation" block shown above the footer.
 * Text: site.json "cta", then site.json "ctaTextByType" for the page type,
 * then the page's own "cta" object. A page hides it with "cta": false.
 *
 * @var array $site
 * @var array $page
 */
$cta = page_cta($page, $site);
?>
    <!-- Final CTA Start -->
    <div class="container-fluid bg-primary newsletter p-0">
        <div class="container p-0">
            <div class="row g-0 align-items-center">
                <div class="col-md-5 ps-lg-0 text-start wow fadeIn" data-wow-delay="0.2s">
                    <img class="img-fluid w-100 h-100" style="object-fit: cover;" src="<?= e($cta['image'] ?? '') ?>"
                        alt="<?= e($cta['imageAlt'] ?? '') ?>" loading="lazy" width="720" height="480">
                </div>
                <div class="col-md-7 py-5 newsletter-text wow fadeIn" data-wow-delay="0.5s">
                    <div class="p-5">
                        <span class="text-uppercase text-primary fw-bold d-block mb-2" style="letter-spacing: 2px;"><?= e($cta['eyebrow'] ?? '') ?></span>
                        <h2 class="h1 mb-4"><?= e($cta['heading'] ?? '') ?></h2>
                        <p class="text-white mb-4"><?= e($cta['text'] ?? '') ?></p>
                        <a href="<?= e($cta['buttonHref'] ?? '/contact.html') ?>" class="btn btn-primary py-3 px-5" data-cta="book-appointment"><?= e($cta['buttonLabel'] ?? 'Book Consultation') ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Final CTA End -->
