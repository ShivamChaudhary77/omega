<?php
/**
 * Blog post card (same markup as js/blog.js and build-blog-index.py).
 *
 * @var array $post  a post summary (see page_summary())
 */
$img = (array) ($post['featuredImage'] ?? []);
?>
        <div class="col-md-6 col-lg-4">
            <a href="<?= e($post['urlPath']) ?>" class="blog-card">
                <div class="blog-card-img">
                    <img src="<?= e($img['src'] ?? '') ?>" alt="<?= e($img['alt'] ?? '') ?>"
                        width="<?= (int) ($img['width'] ?? 1200) ?>" height="<?= (int) ($img['height'] ?? 800) ?>" loading="lazy">
                    <span class="blog-badge"><?= e($post['category'] ?? '') ?></span>
                </div>
                <div class="blog-card-body">
                    <div class="blog-card-meta">
                        <span><i class="bi bi-calendar3"></i><?= e(format_date($post['publishDate'] ?? null)) ?></span>
                        <span><i class="bi bi-clock"></i><?= (int) ($post['readingTimeMinutes'] ?? 0) ?> min read</span>
                    </div>
                    <h3><?= e($post['title'] ?? '') ?></h3>
                    <p><?= e($post['excerpt'] ?? '') ?></p>
                    <span class="blog-card-link">Read Article <i class="bi bi-arrow-right"></i></span>
                </div>
            </a>
        </div>
