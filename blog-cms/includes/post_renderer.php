<?php
/**
 * Renders a full public-facing post page (same header/nav/footer markup and
 * CSS as every other page on the site — copied from single-post.html's
 * shell, kept in sync manually since this project has no shared PHP include
 * for header/footer; see BLOG-README.md for why the existing static pipeline
 * doesn't have one either). Used by:
 *   - blog-cms/public/router.php (real visitors, published posts only)
 *   - blog-cms/admin/blogs/preview.php (authenticated admin preview)
 *   - blog-cms/public/preview.php (token-based preview, unpublished OK)
 *
 * $isPreview forces noindex + shows a "PREVIEW" banner, regardless of the
 * post's own robots_index setting — a preview must never be indexable.
 */

declare(strict_types=1);

require_once __DIR__ . '/seo.php';

/** Converts the editor's [[youtube:ID]] token into a real, safe iframe embed.
 *  The ID is re-validated here (defense in depth) before ever touching the
 *  iframe src — this is the ONLY way a YouTube embed can appear in rendered
 *  content; raw <iframe> tags are stripped by v_sanitize_html at save time. */
function blog_render_content(string $html): string
{
    return (string) preg_replace_callback(
        '/\[\[youtube:([a-zA-Z0-9_-]{11})\]\]/',
        function (array $m): string {
            $id = $m[1];
            return '<div class="ratio ratio-16x9 my-4"><iframe src="https://www.youtube.com/embed/'
                . htmlspecialchars($id, ENT_QUOTES, 'UTF-8')
                . '" title="YouTube video" frameborder="0" allowfullscreen loading="lazy"></iframe></div>';
        },
        $html
    );
}

function render_public_post_page(array $post, array $category, ?array $author, array $related, bool $isPreview = false): void
{
    $e = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
    $categorySlug = $category['slug'];
    $postUrl = seo_post_url($post, $categorySlug);
    $authorName = $author['name'] ?? 'Shivam Kumar';

    $featuredImageUrl = null;
    if (!empty($post['featured_image_id'])) {
        require_once __DIR__ . '/media.php';
        $media = media_find((int) $post['featured_image_id']);
        $featuredImageUrl = $media ? 'https://theomegagroup.in' . $media['file_path'] : null;
    }

    $headTags = seo_render_head_tags($post, $categorySlug, $featuredImageUrl);
    if ($isPreview) {
        // Preview must never be indexable, regardless of the post's own SEO settings.
        $headTags = (string) preg_replace('/<meta name="robots"[^>]*>/', '<meta name="robots" content="noindex, nofollow">', $headTags);
    }
    $articleLd = seo_build_article_ld($post, $categorySlug, $featuredImageUrl, $authorName);
    $breadcrumbLd = seo_build_breadcrumb_ld($category['name'], $categorySlug, $post['title'], $postUrl);
    ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="google-site-verification" content="WfK6bn2Y0riMl83guJlVGhq8GrnN6rbKjyn5Nrk43Ls" />
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-FSNHC0N7BD"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-FSNHC0N7BD');
    </script>
    <!-- Meta Pixel Code -->
    <script>
    !function(f,b,e,v,n,t,s)
    {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s)}(window, document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '857790543995038');
    fbq('track', 'PageView');
    </script>
    <noscript><img height="1" width="1" style="display:none"
    src="https://www.facebook.com/tr?id=857790543995038&ev=PageView&noscript=1"
    /></noscript>
    <!-- End Meta Pixel Code -->
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <?= $headTags ?>
    <link href="/img/favicon.svg" rel="icon" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Space+Grotesk&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/lib/animate/animate.min.css" rel="stylesheet">
    <link href="/css/bootstrap.min.css" rel="stylesheet">
    <link href="/css/style.css" rel="stylesheet">
    <link href="/css/blog.css" rel="stylesheet">
    <?= $articleLd ?>
    <?= $breadcrumbLd ?>
</head>

<body>
    <?php if ($isPreview): ?>
    <div style="background:#0A0A0A; color:#C9A84C; text-align:center; padding:8px; font-size:.85rem; font-family:sans-serif;">
        PREVIEW MODE — this page is not published<?= $post['status'] !== 'published' ? ' (status: ' . $e(ucfirst($post['status'])) . ')' : '' ?> and is not visible to the public or search engines.
    </div>
    <?php endif; ?>

    <div class="container-fluid sticky-top">
        <div class="container">
            <nav class="navbar navbar-expand-lg navbar-light border-bottom border-2 border-white">
                <a href="/index.html" class="navbar-brand">
                    <img class="brand-logo" src="/img/logo.png" alt="The Omega Group logo" width="300" height="42">
                </a>
                <button type="button" class="navbar-toggler ms-auto me-0" data-bs-toggle="collapse" data-bs-target="#navbarCollapse" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarCollapse">
                    <div class="navbar-nav ms-auto">
                        <a href="/index.html" class="nav-item nav-link">Home</a>
                        <a href="/about.html" class="nav-item nav-link">About</a>
                        <a href="/service.html" class="nav-item nav-link">Services</a>
                        <a href="/project.html" class="nav-item nav-link">Projects</a>
                        <a href="/blog.html" class="nav-item nav-link active">Blog</a>
                        <a href="/contact.html" class="nav-item nav-link">Contact</a>
                    </div>
                </div>
            </nav>
        </div>
    </div>

    <article>
        <div class="container-fluid pb-5 hero-header hero-home">
            <div class="container py-5">
                <div class="hero-glass row g-3 p-4 p-lg-5 mx-0">
                    <div class="col-12">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-3">
                                <li class="breadcrumb-item"><a class="text-primary" href="/index.html">Home</a></li>
                                <li class="breadcrumb-item"><a class="text-primary" href="/blog.html">Blog</a></li>
                                <li class="breadcrumb-item text-secondary active" aria-current="page"><?= $e($category['name']) ?></li>
                            </ol>
                        </nav>
                        <h1 class="display-5 mb-0"><?= $e($post['title']) ?></h1>
                        <div class="post-hero-meta-row">
                            <span>By <?php if (isset(SEO_AUTHORS[$authorName])): ?><a href="<?= $e(SEO_AUTHORS[$authorName]['url']) ?>"><?= $e($authorName) ?></a><?php else: ?><?= $e($authorName) ?><?php endif; ?></span>
                            <?php if (!empty($post['published_at'])): ?><span> · Published <?= $e(date('F j, Y', strtotime($post['published_at']))) ?></span><?php endif; ?>
                            <?php if (!empty($post['reading_time'])): ?><span> · <?= (int) $post['reading_time'] ?> min read</span><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($featuredImageUrl): ?>
        <div class="container">
            <div class="post-featured-img">
                <img src="<?= $e($featuredImageUrl) ?>" alt="<?= $e($post['title']) ?>" width="1200" height="675" fetchpriority="high">
            </div>
        </div>
        <?php endif; ?>

        <div class="container py-5">
            <div class="post-layout">
                <div class="post-body">
                    <?= blog_render_content($post['content']) ?>
                </div>

                <div class="post-share">
                    <span>Share this article:</span>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($postUrl) ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-square border-2" aria-label="Share on Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://wa.me/?text=<?= urlencode($post['title'] . ' ' . $postUrl) ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-square border-2" aria-label="Share on WhatsApp"><i class="fab fa-whatsapp"></i></a>
                </div>

                <?php $authorInfo = SEO_AUTHORS[$authorName] ?? null; ?>
                <div class="author-box">
                    <div class="author-box-logo"><?= $e(mb_substr($authorName, 0, 1)) ?></div>
                    <div>
                        <h3>Written by <?php if ($authorInfo): ?><a href="<?= $e($authorInfo['url']) ?>"><?= $e($authorName) ?></a><?php else: ?><?= $e($authorName) ?><?php endif; ?></h3>
                        <?php if ($authorName === 'Shivam Kumar'): ?>
                        <p>Shivam Kumar is a content writer and digital marketing professional with an interest in SEO, technology, and digital growth. He focuses on creating clear, useful, and research-driven content that helps readers understand complex topics in a simple way.</p>
                        <p class="author-box-reviewed">Reviewed for accuracy by The Omega Group's in-house design team — Gurgaon's luxury interior design and turnkey execution studio.</p>
                        <?php else: ?>
                        <p>Written and reviewed by <?= $e($authorName) ?> — Gurgaon's luxury interior design and turnkey execution studio.</p>
                        <?php endif; ?>
                        <?php if ($authorInfo): ?>
                        <div class="author-box-links">
                            <a href="<?= $e($authorInfo['url']) ?>">Full bio <i class="bi bi-arrow-right"></i></a>
                            <?php foreach (($authorInfo['sameAs'] ?? []) as $social): ?>
                            <a href="<?= $e($social) ?>" target="_blank" rel="noopener"><?php if (strpos($social, 'linkedin.com') !== false): ?><i class="fab fa-linkedin-in"></i> LinkedIn<?php else: ?><?= $e($social) ?><?php endif; ?></a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($related): ?>
        <div class="container-fluid bg-light py-5">
            <div class="container py-5">
                <div class="text-center">
                    <h2 class="h1 mb-5">Related <span class="text-uppercase text-primary bg-light px-2">Articles</span></h2>
                </div>
                <div class="row g-4">
                    <?php foreach ($related as $r): ?>
                        <div class="col-md-4">
                            <a href="/<?= $e($r['category_slug']) ?>/<?= $e($r['slug']) ?>/" class="text-decoration-none">
                                <h5><?= $e($r['title']) ?></h5>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="container-fluid bg-primary newsletter p-0">
            <div class="container p-0">
                <div class="row g-0 align-items-center">
                    <div class="col-md-5 ps-lg-0 text-start">
                        <img class="img-fluid w-100 h-100" style="object-fit: cover;" src="/img/cta-consultation.webp" alt="Luxury bedroom suite designed by The Omega Group">
                    </div>
                    <div class="col-md-7 py-5 newsletter-text">
                        <div class="p-5">
                            <span class="text-uppercase text-primary fw-bold d-block mb-2" style="letter-spacing: 2px;">Book Your Appointment</span>
                            <h2 class="h1 mb-4">Free Luxury Design Consultation</h2>
                            <p class="text-white mb-4">Have a question sparked by this article? Talk it through with our design team — no obligation, just clarity on your next step.</p>
                            <a href="/contact.html" class="btn btn-primary py-3 px-5" data-cta="book-appointment">Book Consultation</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </article>

    <div class="container-fluid bg-dark text-white-50 footer pt-5">
        <div class="container py-5">
            <div class="row g-5">
                <div class="col-md-6 col-lg-3">
                    <a href="/index.html" class="d-inline-block mb-3 brand-mark">
                        <img class="brand-logo" src="/img/logo.png" alt="The Omega Group logo" width="300" height="42">
                    </a>
                    <p class="mb-0">The Omega Group is a premium interior design and turnkey execution studio, crafting bespoke homes and commercial spaces for clients who expect nothing less than excellence.</p>
                </div>
                <div class="col-md-6 col-lg-2">
                    <h5 class="text-white mb-4">Quick Links</h5>
                    <a class="btn btn-link" href="/about.html">About Us</a>
                    <a class="btn btn-link" href="/project.html">Our Projects</a>
                    <a class="btn btn-link" href="/service.html">Our Services</a>
                    <a class="btn btn-link" href="/blog.html">Blog</a>
                    <a class="btn btn-link" href="/faqs.html">FAQs</a>
                    <a class="btn btn-link" href="/contact.html">Book a Consultation</a>
                </div>
                <div class="col-md-6 col-lg-2">
                    <h5 class="text-white mb-4">Service Areas</h5>
                    <a class="btn btn-link" href="/interior-designers-gurgaon/">Gurgaon</a>
                    <a class="btn btn-link" href="/interior-designers-dwarka-expressway/">Dwarka Expressway</a>
                    <a class="btn btn-link" href="/interior-designers-sohna-road/">Sohna Road</a>
                    <a class="btn btn-link" href="/interior-designers-sushant-lok/">Sushant Lok</a>
                    <a class="btn btn-link" href="/interior-designers-new-gurgaon/">New Gurgaon</a>
                    <a class="btn btn-link" href="/interior-designers-dlf-gurgaon/">DLF Gurgaon</a>
                    <a class="btn btn-link" href="/interior-designers-golf-course-road/">Golf Course Road</a>
                    <a class="btn btn-link" href="/interior-designers-manesar/">Manesar</a>
                </div>
                <div class="col-md-6 col-lg-2">
                    <h5 class="text-white mb-4">Our Services</h5>
                    <a class="btn btn-link" href="/service.html">Luxury Home Interiors</a>
                    <a class="btn btn-link" href="/service.html">Villa &amp; Apartment Interiors</a>
                    <a class="btn btn-link" href="/service.html">Office &amp; Commercial Interiors</a>
                    <a class="btn btn-link" href="/service.html">Bespoke Furniture</a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <h5 class="text-white mb-4">Get In Touch</h5>
                    <p><i class="fa fa-map-marker-alt me-3"></i>Plot S4 &amp; S5, Main Dwarka Expressway, Near, Daulatabad Chowk, opp. Pillar 109, Gurugram, Haryana 122006</p>
                    <p><i class="fa fa-phone-alt me-3"></i>+91 981 1001 900</p>
                    <p><i class="fa fa-envelope me-3"></i>thegroupomega@gmail.com</p>
                </div>
            </div>
        </div>
        <div class="container">
            <div class="copyright">
                <div class="row">
                    <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                        &copy; <a class="border-bottom" href="/index.html">The Omega Group</a>, All Rights Reserved.
                    </div>
                    <div class="col-md-6 text-center text-md-end">
                        <div class="footer-menu">
                            <a href="/index.html">Home</a>
                            <a href="/about.html">About</a>
                            <a href="/service.html">Services</a>
                            <a href="/blog.html">Blog</a>
                            <a href="/contact.html">Contact</a>
                            <a href="/privacy-policy.html">Privacy Policy</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <a href="#" class="btn btn-lg btn-primary btn-lg-square back-to-top" aria-label="Back to top"><i class="bi bi-arrow-up"></i></a>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/lib/wow/wow.min.js"></script>
    <script src="/lib/easing/easing.min.js"></script>
    <script src="/lib/waypoints/waypoints.min.js"></script>
    <script src="/js/main.js"></script>
    <script src="/js/analytics.js"></script>
</body>
</html>
    <?php
}
