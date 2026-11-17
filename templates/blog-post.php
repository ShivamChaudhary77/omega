<?php
/**
 * Blog post. Same markup as single-post.html after js/single-post.js has
 * run, but rendered on the server so every crawler sees the full article.
 *
 * Page fields: title, category, tags, publishDate, modifiedDate, author,
 * reviewedBy, readingTimeMinutes, featuredImage{src,alt,width,height},
 * excerpt, keyTakeaways[], contentHtml, faqs[{question,answer}],
 * internalLinks[{text,href}], seo{title,description,focusKeyword}
 *
 * @var array $page
 * @var array $site
 */
[$bodyHtml, $toc] = html_heading_ids((string) ($page['contentHtml'] ?? ''));
$img = (array) ($page['featuredImage'] ?? []);
$author = (string) ($page['author'] ?? '');
$authorInfo = author_info($author, $site);
$faqs = (array) ($page['faqs'] ?? []);
$links = unique_links((array) ($page['internalLinks'] ?? []));
$related = related_posts($page);
$shareUrl = rawurlencode(absolute_url($page['urlPath']));
$shareTitle = rawurlencode((string) ($page['title'] ?? ''));
$stats = (array) ($site['stats'] ?? []);
?>
    <article id="post-root">

        <!-- Hero: breadcrumb + h1 + meta row -->
        <div class="container-fluid pb-5 hero-header hero-home">
            <div class="container py-5">
                <div class="hero-glass row g-3 p-4 p-lg-5 mx-0">
                    <div class="col-12">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-3">
                                <li class="breadcrumb-item"><a class="text-primary" href="/index.html">Home</a></li>
                                <li class="breadcrumb-item"><a class="text-primary" href="/blog.html">Blog</a></li>
                                <li class="breadcrumb-item text-secondary active" aria-current="page"><?= e($page['category'] ?? '') ?></li>
                            </ol>
                        </nav>
                        <h1 class="display-5 mb-0 animated slideInLeft"><?= e($page['title'] ?? '') ?></h1>
                        <div class="post-hero-meta-row">
                            <span><i class="bi bi-calendar3"></i><?= e(format_date($page['publishDate'] ?? null)) ?></span>
                            <span><i class="bi bi-clock"></i><?= (int) ($page['readingTimeMinutes'] ?? 0) ?> min read</span>
                            <span><i class="bi bi-folder2"></i><?= e($page['category'] ?? '') ?></span>
                            <span><i class="bi bi-person"></i>By <?= $authorInfo ? '<a href="' . e($authorInfo['url']) . '">' . e($author) . '</a>' : e($author) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<?php if (!empty($img['src'])): ?>
        <div class="container">
            <!-- Featured image -->
            <div class="post-featured-img">
                <img src="<?= e($img['src']) ?>" alt="<?= e($img['alt'] ?? '') ?>" width="<?= (int) ($img['width'] ?? 1200) ?>" height="<?= (int) ($img['height'] ?? 675) ?>" fetchpriority="high">
            </div>
        </div>
<?php endif; ?>

        <div class="container py-5">
            <div class="post-layout">

<?php if (!empty($page['keyTakeaways'])): ?>
                <!-- Key Takeaways -->
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
                <!-- Table of contents -->
                <details class="post-toc">
                    <summary>What's in this article</summary>
                    <ul class="post-toc-list">
<?php foreach ($toc as $item): ?>
                        <li><a href="#<?= e($item['id']) ?>"><?= e($item['text']) ?></a></li>
<?php endforeach; ?>
                    </ul>
                </details>
<?php endif; ?>

                <!-- Body -->
                <div class="post-body">
<?= $bodyHtml ?>

                </div>

<?php if ($faqs): ?>
                <!-- FAQ accordion -->
                <div class="post-faq">
                    <h2 class="h3 mb-4">Frequently Asked Questions</h2>
<?php partial('faq-accordion', ['faqs' => $faqs, 'id' => 'post-faq-accordion', 'itemPrefix' => 'faq']); ?>
                </div>
<?php endif; ?>

<?php if ($links): ?>
                <!-- Internal links -->
                <div class="post-links-block">
                    <h2>Continue Reading</h2>
                    <ul>
<?php foreach ($links as $link): ?>
                        <li><a href="<?= e($link['href']) ?>"><?= e($link['text']) ?></a></li>
<?php endforeach; ?>
                    </ul>
                </div>
<?php endif; ?>

                <!-- Share -->
                <div class="post-share">
                    <span>Share this article:</span>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrl ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-square border-2" aria-label="Share on Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://twitter.com/intent/tweet?url=<?= $shareUrl ?>&amp;text=<?= $shareTitle ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-square border-2" aria-label="Share on X"><i class="fab fa-x-twitter"></i></a>
                    <a href="https://wa.me/?text=<?= $shareTitle ?>%20<?= $shareUrl ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-square border-2" aria-label="Share on WhatsApp"><i class="fab fa-whatsapp"></i></a>
                    <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $shareUrl ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-square border-2" aria-label="Share on LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                </div>

<?php if ($authorInfo): ?>
                <!-- Author / brand credibility box -->
                <div class="author-box">
                    <div class="author-box-logo"><?= e(mb_substr($author, 0, 1)) ?></div>
                    <div>
                        <h3>Written by <a href="<?= e($authorInfo['url']) ?>"><?= e($author) ?></a></h3>
                        <p><?= e($authorInfo['bio'] ?? '') ?></p>
<?php if (!empty($authorInfo['reviewedNote'])): ?>
                        <p class="author-box-reviewed"><?= e($authorInfo['reviewedNote']) ?></p>
<?php endif; ?>
                        <ul class="author-box-stats">
                            <li><strong><?= e($stats['yearsExperience'] ?? '') ?></strong>Years Experience</li>
                            <li><strong><?= e($stats['projectsDelivered'] ?? '') ?></strong>Projects Delivered</li>
                            <li><strong><?= e($stats['onTimeDeliveryRate'] ?? '') ?></strong>On-Time Delivery</li>
                            <li><strong><?= e($stats['qcChecksPerProject'] ?? '') ?></strong>QC Checks</li>
                        </ul>
                        <div class="author-box-links">
                            <a href="<?= e($authorInfo['url']) ?>">Full bio <i class="bi bi-arrow-right"></i></a>
<?php foreach ((array) ($authorInfo['sameAs'] ?? []) as $profile): ?>
<?php if (str_contains($profile, 'linkedin.com')): ?>
                            <a href="<?= e($profile) ?>" target="_blank" rel="noopener"><i class="fab fa-linkedin-in"></i> LinkedIn</a>
<?php endif; ?>
<?php endforeach; ?>
                        </div>
                    </div>
                </div>
<?php endif; ?>

            </div>
        </div>

<?php if ($related): ?>
        <!-- Related posts -->
        <div class="container-fluid bg-light py-5">
            <div class="container py-5">
                <div class="text-center wow fadeIn" data-wow-delay="0.1s">
                    <h2 class="h1 mb-5">Related <span class="text-uppercase text-primary bg-light px-2">Articles</span></h2>
                </div>
                <div class="row g-4">
<?php foreach ($related as $post) { partial('post-card', ['post' => $post]); } ?>
                </div>
            </div>
        </div>
<?php endif; ?>

    </article>
