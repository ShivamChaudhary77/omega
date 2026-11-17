<?php
/**
 * Block "blog-listing": the blog page's pinned latest post, category
 * filter, search box and paginated grid. The first page is rendered on the
 * server (crawlable); js/blog.js then handles filter/search/pagination
 * using the always-current /api/posts.json feed.
 *
 * Fields: none required.
 *
 * @var array $block
 */
$posts = posts_published();
$pinned = $posts[0] ?? null;
$pageSize = 9;
?>
    <!-- Blog Grid Start -->
    <div class="container-fluid py-5">
        <div class="container py-5">
<?php if ($pinned): $img = (array) ($pinned['featuredImage'] ?? []); ?>
            <div id="blog-pinned" class="mb-5">
        <a href="<?= e($pinned['urlPath']) ?>" class="blog-pinned-card">
            <div class="blog-pinned-card-img">
                <img src="<?= e($img['src'] ?? '') ?>" alt="<?= e($img['alt'] ?? '') ?>"
                    width="<?= (int) ($img['width'] ?? 1200) ?>" height="<?= (int) ($img['height'] ?? 800) ?>">
            </div>
            <div class="blog-pinned-card-body">
                <span class="blog-badge">Latest Article</span>
                <h2 class="h3"><?= e($pinned['title'] ?? '') ?></h2>
                <p><?= e($pinned['excerpt'] ?? '') ?></p>
            </div>
        </a></div>
<?php endif; ?>
            <!-- Filter + Search -->
            <div class="blog-filter-bar">
                <div class="blog-filter-pills" id="blog-filter-pills" role="group" aria-label="Filter posts by category">
                    <button type="button" class="blog-filter-pill active" data-category="all">All Posts</button>
                </div>
                <div class="blog-search">
                    <i class="bi bi-search"></i>
                    <input type="search" id="blog-search-input" placeholder="Search articles…" aria-label="Search blog posts">
                </div>
            </div>
            <div class="row g-4" id="blog-grid" aria-live="polite">
<?php foreach (array_slice($posts, 0, $pageSize) as $post) { partial('post-card', ['post' => $post]); } ?>
            </div>
            <div class="blog-empty-state d-none" id="blog-empty-state">
                <i class="bi bi-search fa-2x mb-3 d-block" style="font-size:2rem;"></i>
                <h4>No articles found</h4>
                <p class="mb-0">Try a different search term or category.</p>
            </div>
            <!-- Pagination -->
            <nav class="blog-pagination" id="blog-pagination" aria-label="Blog pagination"></nav>
        </div>
    </div>
    <!-- Blog Grid End -->
    <script>window.OMEGA_BLOG_DATA_URL = "/api/posts.json";</script>
