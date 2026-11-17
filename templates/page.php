<?php
/**
 * Simple page: title band, rich-text sections, optional FAQs.
 * Also the fallback for page types whose own template arrives in Phase 2.
 *
 * Page fields used:
 *   h1, breadcrumb (optional), intro (optional HTML),
 *   sections: [{heading, html}], faqs: [{question, answer}]
 *
 * @var array $page
 * @var array $site
 */
$sections = (array) ($page['sections'] ?? []);
?>
<?php partial('hero', ['page' => $page]); ?>

    <!-- Page Content Start -->
    <div class="container-fluid py-5">
        <div class="container py-5" style="max-width: 960px;">
<?php if (!empty($page['intro'])): ?>
            <div class="lead mb-5"><?= $page['intro'] ?></div>
<?php endif; ?>
<?php foreach ($sections as $section): ?>
            <section class="mb-5">
<?php if (!empty($section['heading'])): ?>
                <h2 class="mb-4"><?= e($section['heading']) ?></h2>
<?php endif; ?>
                <?= $section['html'] ?? '' ?>

            </section>
<?php endforeach; ?>
        </div>
    </div>
    <!-- Page Content End -->

<?php partial('faq', ['faqs' => $page['faqs'] ?? [], 'id' => 'faq-' . $page['id']]); ?>
