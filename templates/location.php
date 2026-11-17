<?php
/**
 * Location page (the 2 pillar + 6 satellite "interior designer in X" pages).
 * Same markup as scripts/build-location-pages.py produced, with the table
 * of contents built on the server instead of by js/toc.js.
 *
 * Page fields: h1, heroImage, breadcrumb[{name,path}], directAnswer,
 * facts[{label,value}], keyTakeaways[], sections[{heading, html}],
 * faqs[{question,answer}], internalLinks[{text,href}], ctaContext
 *
 * @var array $page
 * @var array $site
 */
$sections = [];
$toc = [];
$used = [];
foreach ((array) ($page['sections'] ?? []) as $section) {
    $heading = (string) ($section['heading'] ?? '');
    $base = slugify_like_js($heading);
    $id = $base;
    $n = 1;
    while ($id === '' || isset($used[$id])) {
        $id = $base . '-' . $n++;
    }
    $used[$id] = true;
    $sections[] = ['id' => $id, 'heading' => $heading, 'html' => (string) ($section['html'] ?? '')];
    $toc[] = ['id' => $id, 'text' => $heading];
}
$faqs = (array) ($page['faqs'] ?? []);
$links = unique_links((array) ($page['internalLinks'] ?? []));
?>
<?php partial('hero', ['page' => $page, 'variant' => 'wide']); ?>

    <div class="container py-5">
        <div class="post-layout">
<?php if (!empty($page['directAnswer'])): ?>
            <p class="lead-answer"><?= e($page['directAnswer']) ?></p>
<?php endif; ?>
<?php if (!empty($page['facts'])): ?>
            <div class="fact-strip">
<?php foreach ($page['facts'] as $fact): ?>
                <span class="fact-pill"><strong><?= e($fact['value'] ?? '') ?></strong> <?= e($fact['label'] ?? '') ?></span>
<?php endforeach; ?>
            </div>
<?php endif; ?>

<?php if (!empty($page['keyTakeaways'])): ?>
            <div class="key-takeaways-box">
                <h2><i class="bi bi-lightbulb"></i> Key Takeaways</h2>
                <ul>
<?php foreach ($page['keyTakeaways'] as $item): ?>
                    <li><?= e($item) ?></li>
<?php endforeach; ?>
                </ul>
            </div>
<?php endif; ?>

<?php if (count($toc) >= 2): ?>
            <details class="post-toc">
                <summary>What's in this page</summary>
                <ul class="post-toc-list">
<?php foreach ($toc as $item): ?>
                    <li><a href="#<?= e($item['id']) ?>"><?= e($item['text']) ?></a></li>
<?php endforeach; ?>
                </ul>
            </details>
<?php endif; ?>

            <div class="post-body">
<?php foreach ($sections as $section): ?>
                <h2 id="<?= e($section['id']) ?>"><?= e($section['heading']) ?></h2>
<?= $section['html'] ?>

<?php endforeach; ?>
            </div>

<?php if ($faqs): ?>
            <div class="post-faq">
                <h2 class="h3 mb-4">Frequently Asked Questions</h2>
<?php partial('faq-accordion', ['faqs' => $faqs, 'id' => 'faq-' . $page['id']]); ?>
            </div>
<?php endif; ?>

<?php if ($links): ?>
            <div class="post-links-block">
                <h2>Explore More</h2>
                <ul>
<?php foreach ($links as $link): ?>
                    <li><a href="<?= e($link['href']) ?>"><?= e($link['text']) ?></a></li>
<?php endforeach; ?>
                </ul>
            </div>
<?php endif; ?>
        </div>
    </div>
