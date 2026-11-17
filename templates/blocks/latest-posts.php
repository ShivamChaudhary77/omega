<?php
/**
 * Block "latest-posts": the newest blog posts as cards (homepage "From The
 * Blog"). Always current: new posts appear automatically.
 *
 * Fields: headingHtml, intro, count (default 4)
 *
 * @var array $block
 */
$posts = array_slice(posts_published(), 0, max(1, (int) ($block['count'] ?? 4)));
?>
    <!-- From The Blog Start -->
    <div class="container-fluid py-5">
        <div class="container py-5">
            <div class="text-center wow fadeIn" data-wow-delay="0.1s">
                <h2 class="h1 mb-3"><?= $block['headingHtml'] ?? 'From The <span class="text-uppercase text-primary bg-light px-2">Blog</span>' ?></h2>
<?php if (!empty($block['intro'])): ?>
                <p class="mx-auto mb-5" style="max-width: 600px;"><?= e($block['intro']) ?></p>
<?php endif; ?>
            </div>
            <div class="row g-4">
<?php foreach ($posts as $post) { partial('post-card', ['post' => $post]); } ?>
            </div>
<?php if (!empty($block['buttonLabel'])): ?>
            <div class="text-center mt-5 wow fadeIn" data-wow-delay="0.1s">
                <a href="<?= e($block['buttonHref'] ?? '/blog.html') ?>" class="btn btn-primary py-3 px-5"><?= e($block['buttonLabel']) ?></a>
            </div>
<?php endif; ?>
        </div>
    </div>
    <!-- From The Blog End -->
