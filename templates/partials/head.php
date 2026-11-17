<?php
/**
 * <head> for every page: tracking (GSC, GA4, Meta Pixel), SEO tags,
 * stylesheets and JSON-LD. Mirrors the head of the current static pages.
 *
 * @var array $head  from page_head()
 * @var array $site  site.json
 */
$tracking = (array) ($site['tracking'] ?? []);
$ga4 = (string) ($tracking['ga4MeasurementId'] ?? '');
$pixel = (string) ($tracking['metaPixelId'] ?? '');
$verification = (string) ($tracking['googleSiteVerification'] ?? '');
?>
<head>
    <meta charset="utf-8">
<?php if ($verification !== ''): ?>

    <!-- Google Search Console verification -->
    <meta name="google-site-verification" content="<?= e($verification) ?>" />
<?php endif; ?>
<?php if ($ga4 !== ''): ?>

    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga4) ?>"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', <?= json_encode($ga4, JSON_HEX_TAG) ?>);
    </script>
<?php endif; ?>
<?php if ($pixel !== ''): ?>
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
    fbq('init', <?= json_encode($pixel, JSON_HEX_TAG) ?>);
    fbq('track', 'PageView');
    </script>
    <noscript><img height="1" width="1" style="display:none"
    src="https://www.facebook.com/tr?id=<?= e($pixel) ?>&amp;ev=PageView&amp;noscript=1"
    /></noscript>
    <!-- End Meta Pixel Code -->
<?php endif; ?>
    <title><?= e($head['title']) ?></title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
<?php if ($head['keywords'] !== ''): ?>
    <meta name="keywords" content="<?= e($head['keywords']) ?>">
<?php endif; ?>
<?php if ($head['description'] !== ''): ?>
    <meta name="description" content="<?= e($head['description']) ?>">
<?php endif; ?>
    <meta name="robots" content="<?= e($head['robots']) ?>">
    <link rel="canonical" href="<?= e($head['canonical']) ?>">

    <!-- Open Graph / Twitter -->
    <meta property="og:type" content="<?= e($head['ogType']) ?>">
    <meta property="og:site_name" content="<?= e($site['business']['name'] ?? '') ?>">
    <meta property="og:title" content="<?= e($head['title']) ?>">
<?php if ($head['description'] !== ''): ?>
    <meta property="og:description" content="<?= e($head['description']) ?>">
<?php endif; ?>
    <meta property="og:url" content="<?= e($head['canonical']) ?>">
<?php if ($head['ogImage'] !== ''): ?>
    <meta property="og:image" content="<?= e($head['ogImage']) ?>">
<?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($head['title']) ?>">
<?php if ($head['description'] !== ''): ?>
    <meta name="twitter:description" content="<?= e($head['description']) ?>">
<?php endif; ?>

    <!-- Favicon -->
    <link href="/img/favicon.svg" rel="icon" type="image/svg+xml">

    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Space+Grotesk&display=swap" rel="stylesheet">

    <!-- Icon Font Stylesheet -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

<?php if ($head['animateCss']): ?>
    <!-- Libraries Stylesheet -->
    <link href="/lib/animate/animate.min.css" rel="stylesheet">
<?php endif; ?>
<?php if ($head['owlCarousel']): ?>
    <link href="/lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">
<?php endif; ?>

    <!-- Customized Bootstrap Stylesheet -->
    <link href="/css/bootstrap.min.css" rel="stylesheet">

    <!-- Template Stylesheet -->
    <link href="/css/style.css" rel="stylesheet">
<?php foreach ($head['extraCss'] as $css): ?>
    <link href="<?= e($css) ?>" rel="stylesheet">
<?php endforeach; ?>
<?php if ($head['preload'] !== ''): ?>
    <link rel="preload" as="image" href="<?= e($head['preload']) ?>">
<?php endif; ?>
<?php if ($head['headHtml'] !== ''): ?>
    <?= $head['headHtml'] ?>

<?php endif; ?>
<?php foreach ($head['schema'] as $schema): ?>
    <?= json_ld($schema) ?>

<?php endforeach; ?>
</head>
