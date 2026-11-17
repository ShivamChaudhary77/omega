<?php
/**
 * Project detail page (/project/<slug>/). Same markup as
 * scripts/build-project-pages.py produced.
 *
 * Page fields: name, projectType, configuration, location, sizeSqFt,
 * budgetBand, timeline, image, imageAlt, gallery[], h1, leadAnswer,
 * sections[{heading, html}], relatedBlogPosts[{text,href}], sortOrder,
 * card{title, alt} (used on the homepage and the projects grid)
 *
 * A "gallery-only" project simply leaves leadAnswer and sections empty.
 *
 * @var array $page
 * @var array $site
 */
$name = (string) ($page['name'] ?? '');
$type = (string) ($page['projectType'] ?? '');
$h1 = (string) ($page['h1'] ?? trim($name . ' — ' . $type . (!empty($page['location']) ? ', ' . $page['location'] : '')));
$gallery = array_values((array) ($page['gallery'] ?? []));
$related = array_slice(array_values(array_filter(projects_published(), fn ($p) => $p['id'] !== $page['id'])), 0, 3);
$links = [];
foreach ($related as $p) {
    $links[] = ['text' => ($p['name'] ?? '') . ' — ' . ($p['projectType'] ?? ''), 'href' => $p['urlPath']];
}
foreach ((array) ($page['relatedBlogPosts'] ?? []) as $post) {
    $links[] = $post;
}
$links[] = ['text' => 'See all our projects', 'href' => '/project.html'];
$links[] = ['text' => 'Start a project like this one', 'href' => '/contact.html'];
$links = unique_links($links);
?>
<?php partial('hero', ['page' => $page, 'h1' => $h1, 'variant' => 'wide']); ?>

<?php if (!empty($page['image'])): ?>
    <div class="container">
        <div class="post-featured-img">
            <img src="<?= e($page['image']) ?>" alt="<?= e($page['imageAlt'] ?? ($name . ' — ' . $type . ' designed by ' . ($site['business']['name'] ?? ''))) ?>" width="1200" height="675" fetchpriority="high">
        </div>
    </div>
<?php endif; ?>

    <div class="container py-5">
        <div class="post-layout">
<?php if (!empty($page['leadAnswer'])): ?>
            <p class="lead-answer"><?= e($page['leadAnswer']) ?></p>
<?php endif; ?>
            <div class="fact-strip">
<?php if (!empty($page['configuration'])): ?>
                <span class="fact-pill"><strong><?= e($page['configuration']) ?></strong></span>
<?php endif; ?>
<?php if (!empty($page['location'])): ?>
                <span class="fact-pill"><?= e($page['location']) ?></span>
<?php endif; ?>
<?php if (!empty($page['sizeSqFt'])): ?>
                <span class="fact-pill"><strong><?= e($page['sizeSqFt']) ?> sq. ft.</strong></span>
<?php endif; ?>
<?php if (!empty($page['budgetBand'])): ?>
                <span class="fact-pill"><strong><?= e($page['budgetBand']) ?></strong> Budget</span>
<?php endif; ?>
<?php if (!empty($page['timeline'])): ?>
                <span class="fact-pill"><strong><?= e($page['timeline']) ?></strong></span>
<?php endif; ?>
<?php if ($type !== ''): ?>
                <span class="fact-pill"><?= e($type) ?></span>
<?php endif; ?>
            </div>
<?php if (!empty($page['sections'])): ?>
            <div class="post-body">
<?php foreach ($page['sections'] as $section): ?>
                <h2><?= e($section['heading'] ?? '') ?></h2>
                <?= $section['html'] ?? '' ?>

<?php endforeach; ?>
            </div>
<?php endif; ?>
<?php if ($gallery): ?>
            <div class="post-body">
                <h2>Project Gallery (<?= count($gallery) ?> photos)</h2>
                <div class="project-gallery" data-lightbox-gallery>
<?php foreach ($gallery as $i => $src): ?>
                    <a href="<?= e($src) ?>" class="project-gallery-item" data-lightbox-src="<?= e($src) ?>"><img src="<?= e($src) ?>" alt="<?= e($name . ' — ' . $type . ', photo ' . ($i + 1) . ' of ' . count($gallery)) ?>" loading="<?= $i < 4 ? 'eager' : 'lazy' ?>"></a>
<?php endforeach; ?>
                </div>
            </div>
<?php endif; ?>
            <div class="post-links-block">
                <h2>Explore More</h2>
                <ul>
<?php foreach ($links as $link): ?>
                    <li><a href="<?= e($link['href']) ?>"><?= e($link['text']) ?></a></li>
<?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
