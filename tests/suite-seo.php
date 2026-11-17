<?php
/**
 * Suite: SEO automation (Phase 5): live sitemaps and llms.txt, redirects
 * kept consistent with pages, link checking and the editor's SEO checks.
 */

declare(strict_types=1);

foreach (glob(REPO_DIR . '/content/pages/*.json') as $file) {
    copy($file, "$tmp/pages/" . basename($file));
}
page_index_rebuild();

echo "Live files\n";
$sm = get('/sitemap.xml');
check('/sitemap.xml served as XML', $sm['status'] === 200 && str_starts_with($sm['headers']['Content-Type'] ?? '', 'application/xml'));
check('/sitemap.xml is valid XML', @simplexml_load_string($sm['body']) !== false);
check('/sitemap-blog.xml identical to the current static file', get('/sitemap-blog.xml')['body'] === static_reference('/sitemap-blog.xml'));
check('/llms.txt identical to the current static file', get('/llms.txt')['body'] === static_reference('/llms.txt'));
preg_match_all('#<loc>([^<]+)</loc>#', static_reference('/sitemap.xml'), $old);
preg_match_all('#<loc>([^<]+)</loc>#', $sm['body'], $new);
check('/sitemap.xml keeps every URL and order of the static file', array_values(array_intersect($new[1], $old[1])) === $old[1] && count(array_diff($old[1], $new[1])) === 0);
check('/sitemap.xml adds the author page (was missing)', in_array('https://theomegagroup.in/author/shivam-kumar/', $new[1], true));
check('/sitemap.xml has no blog posts, 404 or noindex pages', !preg_grep('#/(kitchen-design|home-residential-interiors)/|404|free-design-consultation#', $new[1]));

$post = page_load('bespoke-interiors-gurgaon');
$post['status'] = 'draft';
page_save($post);
check('drafts leave the sitemap at once', !str_contains(get('/sitemap-blog.xml')['body'], 'bespoke-interiors-gurgaon'));
$post['status'] = 'published';
page_save($post);
check('published posts appear in the sitemap', str_contains(get('/sitemap-blog.xml')['body'], 'bespoke-interiors-gurgaon'));

$site = site();
$site['phoneDisplay'] = '+91 111 2222 333';
site_save($site);
check('llms.txt follows the settings', str_contains(get('/llms.txt')['body'], 'Phone: +91 111 2222 333'));

echo "\nRedirects and pages\n";
redirect_set('/some-old-post/', '/blog.html');
check('dashboard redirect works', get('/some-old-post/')['status'] === 301);
$new = $post;
$new['id'] = 'some-old-post';
$new['urlPath'] = '/some-old-post/';
$new['seo']['title'] = 'Reusing an address';
page_save($new);
check('a page saved at a redirected address removes the redirect', redirect_find('/some-old-post/') === null && get('/some-old-post/')['status'] === 200);
page_delete('some-old-post');
redirect_set('/some-old-post/', '/blog.html');
page_restore_from_trash('some-old-post');
check('restoring a page removes the redirect at its address', get('/some-old-post/')['status'] === 200);

echo "\nLink checking\n";
check('existing page link is fine', internal_link_ok('/interior-designers-gurgaon/'));
check('static file link is fine', internal_link_ok('/img/logo.png'));
check('historic .htaccess redirect is fine', internal_link_ok('/our-services/'));
check('generated files are fine', internal_link_ok('/sitemap.xml'));
check('missing page is reported', !internal_link_ok('/no-such-page/'));
check('query and hash ignored', internal_link_ok('/blog.html?x=1#top'));

echo "\nSEO checks\n";
$checks = fn ($p) => array_column(seo_checks($p), 'text');
$p = page_load('interior-designers-gurgaon');
$p['seo']['title'] = str_repeat('Very long title ', 6);
$p['seo']['description'] = '';
$texts = implode(' | ', $checks($p));
check('long title warned', str_contains($texts, 'cuts it off after about 60'));
check('missing description warned', str_contains($texts, 'Add a meta description'));
$p = page_load('interior-designers-gurgaon');
$p['seo']['title'] = page_load('interior-designers-manesar')['seo']['title'];
check('duplicate title warned', str_contains(implode(' ', $checks($p)), 'Another page has the same SEO title'));
$p = page_load('bespoke-interiors-gurgaon');
$p['seo']['focusKeyword'] = page_load('interior-design-warranty-india')['seo']['focusKeyword'];
check('competing focus keyword warned', str_contains(implode(' ', $checks($p)), 'already targets'));
$p = page_load('bespoke-interiors-gurgaon');
$p['contentHtml'] .= '<p><a href="/missing-page/">x</a><img src="/img/nope.webp"></p>';
$texts = implode(' ', $checks($p));
check('broken link and image reported', str_contains($texts, '/missing-page/') && str_contains($texts, '/img/nope.webp'));
check('image without alt text reported', str_contains($texts, 'have no description'));
check('warnings listed before good news', seo_checks($p)[0]['level'] === 'warn');
check('suggested redirect for a deleted post is the blog', suggested_redirect_target(['type' => 'blog-post']) === '/blog.html');
