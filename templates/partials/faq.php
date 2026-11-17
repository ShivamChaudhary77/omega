<?php
/**
 * Full-width FAQ section (heading + accordion), as on the service page.
 *
 * @var array       $faqs         [['question' => ..., 'answer' => ...], ...]
 * @var string      $id           unique accordion id, e.g. "faq-service"
 * @var string|null $title        plain-text heading
 * @var string|null $headingHtml  heading HTML (wins over $title)
 */
if (empty($faqs)) {
    return;
}
$headingHtml = $headingHtml ?? null;
$title = $title ?? 'Frequently Asked Questions';
?>
    <!-- FAQ Start -->
    <div class="container-fluid py-5">
        <div class="container py-5" style="max-width: 860px;">
            <div class="text-center wow fadeIn" data-wow-delay="0.1s">
                <h2 class="h1 mb-5"><?= $headingHtml ?? e($title) ?></h2>
            </div>
            <div class="post-faq mt-0">
<?php partial('faq-accordion', ['faqs' => $faqs, 'id' => $id]); ?>
            </div>
        </div>
    </div>
    <!-- FAQ End -->
