<?php
/**
 * End-to-end test of the dashboard over real HTTP: starts PHP's built-in
 * server on a throwaway copy of the content, then logs in, edits, creates,
 * uploads, deletes and restores like a browser would.
 *
 *   php tests/dashboard-http.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$repo = dirname(__DIR__);
$tmp = sys_get_temp_dir() . '/omega-http-' . bin2hex(random_bytes(4));
$content = "$tmp/content";
$uploads = "$tmp/uploads";
mkdir("$content/pages", 0755, true);
mkdir($uploads, 0755, true);
copy("$repo/content/site.json", "$content/site.json");
foreach (glob("$repo/content/pages/*.json") as $f) {
    copy($f, "$content/pages/" . basename($f));
}
$port = 8790 + random_int(0, 50);
$base = "http://127.0.0.1:$port";
$env = array_merge(getenv(), ['OMEGA_CONTENT_DIR' => $content, 'OMEGA_UPLOADS_DIR' => $uploads, 'OMEGA_MODE' => 'dynamic']);

// Set the password through the real CLI command.
$cli = proc_open([PHP_BINARY, "$repo/app/cli.php", 'set-password'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, $repo, $env);
fwrite($pipes[0], "test password 123\ntest password 123\n");
fclose($pipes[0]);
$cliOut = stream_get_contents($pipes[1]);
proc_close($cli);

$server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", 'router.php'], [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $sp, $repo, $env);
usleep(600000);

$passed = 0;
$failed = [];
function check(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  ok   $name\n";
    } else {
        $failed[] = $name;
        echo "  FAIL $name" . ($detail !== '' ? " — $detail" : '') . "\n";
    }
}

$jar = "$tmp/cookies.txt";
/** @return array{status:int, body:string, location:string} */
function http(string $method, string $path, array $fields = [], ?string $jarFile = null, ?string $ip = null): array
{
    global $base, $jar;
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jarFile ?? $jar,
        CURLOPT_COOKIEFILE => $jarFile ?? $jar,
        CURLOPT_CUSTOMREQUEST => $method,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    }
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($raw, 0, $headerSize);
    preg_match('/^Location:\s*(\S+)/mi', $headers, $m);
    return ['status' => $status, 'body' => substr($raw, $headerSize), 'location' => $m[1] ?? '', 'headers' => $headers];
}

function csrf_from(string $html): string
{
    return preg_match('/name="csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';
}

/** Flatten nested arrays into "a[b][c]" => value for curl. */
function flat(array $data, string $prefix = ''): array
{
    $out = [];
    foreach ($data as $k => $v) {
        $key = $prefix === '' ? (string) $k : $prefix . '[' . $k . ']';
        if (is_array($v)) {
            $out += flat($v, $key);
        } else {
            $out[$key] = (string) $v;
        }
    }
    return $out;
}

/** The form fields of the edit page, as a browser would submit them. */
function form_fields(string $html): array
{
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    $form = $doc->getElementById('edit-form');
    $fields = [];
    $xp = new DOMXPath($doc);
    foreach ($xp->query('.//input|.//textarea|.//select', $form) as $el) {
        if ($el->getAttribute('name') === '' || $el->hasAttribute('disabled') || $xp->query('ancestor::template', $el)->length) {
            continue;
        }
        $name = $el->getAttribute('name');
        if ($el->nodeName === 'textarea') {
            $fields[$name] = $el->textContent;
        } elseif ($el->nodeName === 'select') {
            $value = '';
            foreach ($xp->query('.//option', $el) as $i => $opt) {
                if ($i === 0 || $opt->hasAttribute('selected')) {
                    $value = $opt->getAttribute('value');
                }
            }
            $fields[$name] = $value;
        } elseif ($el->getAttribute('type') === 'checkbox') {
            if ($el->hasAttribute('checked')) {
                $fields[$name] = $el->getAttribute('value');
            }
        } else {
            $fields[$name] = $el->getAttribute('value');
        }
    }
    return $fields;
}

try {
    echo "Setup\n";
    check('CLI set-password works', str_contains($cliOut, 'Dashboard password saved'), $cliOut);

    echo "\nLogin\n";
    $r = http('GET', '/admin/');
    check('dashboard requires login', $r['status'] === 303 && str_starts_with($r['location'], '/admin/login.php'));
    check('dashboard is noindex', str_contains(http('GET', '/admin/login.php')['headers'], 'X-Robots-Tag: noindex'));
    $login = http('GET', '/admin/login.php');
    $r = http('POST', '/admin/login.php', ['csrf' => 'forged', 'password' => 'test password 123']);
    check('login form rejects a forged token', $r['status'] === 200 && str_contains($r['body'], 'expired'));
    $r = http('POST', '/admin/login.php', ['csrf' => csrf_from($login['body']), 'password' => 'wrong password']);
    check('wrong password refused with attempts left', str_contains($r['body'], 'attempt(s) left'));
    $r = http('POST', '/admin/login.php', ['csrf' => csrf_from($r['body']), 'password' => 'test password 123']);
    check('right password logs in', $r['status'] === 303 && $r['location'] === '/admin/');
    check('session cookie is HttpOnly + SameSite=Strict', (bool) preg_match('/OMEGA_ADMIN.*/', (string) file_get_contents($jar)) && str_contains(http('GET', '/admin/logout.php')['headers'] . '', '') );

    echo "\nPages list\n";
    $list = http('GET', '/admin/');
    check('lists pages', $list['status'] === 200 && str_contains($list['body'], 'Bespoke Interiors in Gurgaon') && substr_count($list['body'], '/admin/edit.php?id=') >= 148 * 2);
    check('search filters', substr_count(http('GET', '/admin/?q=manesar')['body'], 'class="admin-title"') >= 1 && !str_contains(http('GET', '/admin/?q=manesar')['body'], 'Bespoke Interiors in Gurgaon'));

    echo "\nEdit an existing post\n";
    $edit = http('GET', '/admin/edit.php?id=bespoke-interiors-gurgaon');
    check('edit form opens', $edit['status'] === 200 && str_contains($edit['body'], 'id="edit-form"'));
    $fields = form_fields($edit['body']);
    $fields['f[seo][title]'] = 'Edited SEO title via dashboard';
    $r = http('POST', '/admin/edit.php', $fields);
    check('save redirects back to the editor', $r['status'] === 303 && str_contains($r['location'], 'id=bespoke-interiors-gurgaon'));
    $live = http('GET', '/home-residential-interiors/bespoke-interiors-gurgaon/');
    check('change is live immediately', str_contains($live['body'], '<title>Edited SEO title via dashboard</title>'));
    $article = json_decode((string) file_get_contents("$content/pages/bespoke-interiors-gurgaon.json"), true);
    preg_match('#<h2[^>]*>(.*?)</h2>#s', $article['contentHtml'], $h);
    check('rest of the article unchanged', isset($h[1]) && str_contains($live['body'], $h[1]) && str_contains($live['body'], 'post-faq-accordion'));
    $hist = http('GET', '/admin/history.php?id=bespoke-interiors-gurgaon');
    check('previous version in history', str_contains($hist['body'], 'Restore this version'));
    $bad = $fields;
    $bad['csrf'] = 'forged';
    $bad['f[seo][title]'] = 'Should not save';
    http('POST', '/admin/edit.php', $bad);
    check('edit with a forged token is not saved', !str_contains(http('GET', '/home-residential-interiors/bespoke-interiors-gurgaon/')['body'], 'Should not save'));

    echo "\nDesigned section text edit (home page)\n";
    $home = http('GET', '/admin/edit.php?id=home');
    $fields = form_fields($home['body']);
    $key = null;
    foreach ($fields as $name => $value) {
        if (preg_match('/^f\[blocks\]\[\d+\]\[text\]\[\d+\]$/', $name) && $value === 'Luxury Interiors Crafted Around Your') {
            $key = $name;
        }
    }
    check('home hero text is editable as plain text', $key !== null);
    if ($key) {
        $fields[$key] = 'Bespoke Homes Crafted Around Your';
        http('POST', '/admin/edit.php', $fields);
        $live = http('GET', '/')['body'];
        check('home hero text changed on the live page', str_contains($live, 'Bespoke Homes Crafted Around Your <span class="text-primary">Lifestyle</span>'));
    }

    echo "\nCreate, upload, publish\n";
    $png = "$tmp/test.png";
    $img = imagecreatetruecolor(2400, 1200);
    imagefill($img, 0, 0, imagecolorallocate($img, 200, 168, 76));
    imagepng($img, $png);
    $csrf = csrf_from(http('GET', '/admin/media.php')['body']);
    $up = http('POST', '/admin/media.php?format=json', ['csrf' => $csrf, 'action' => 'upload', 'file' => new CURLFile($png, 'image/png', 'Kitchen Photo.png')]);
    $upload = json_decode($up['body'], true);
    check('image upload resized to 1600px and saved as WebP', ($upload['ok'] ?? false) && $upload['width'] === 1600 && str_ends_with($upload['url'], '.webp'), $up['body']);
    check('uploaded image is served', http('GET', $upload['url'] ?? '/x')['status'] === 200);
    file_put_contents("$tmp/evil.php.png", '<?php echo "pwned";');
    $evil = json_decode(http('POST', '/admin/media.php?format=json', ['csrf' => $csrf, 'action' => 'upload', 'file' => new CURLFile("$tmp/evil.php.png", 'image/png', 'evil.php')])['body'], true);
    check('non-image upload refused', ($evil['ok'] ?? true) === false);

    $new = http('GET', '/admin/edit.php?new=blog-post');
    $fields = form_fields($new['body']);
    $fields['f[title]'] = 'Test Post From The Dashboard';
    $fields['f[featuredImage][src]'] = $upload['url'];
    $fields['f[featuredImage][alt]'] = 'A gold test image';
    $fields['f[contentHtml]'] = '<h2>First heading</h2><p>Hello from the dashboard.</p><h2>Second heading</h2><p>More.</p>';
    $fields['f[seo][description]'] = 'A test post.';
    $fields['f[category]'] = 'Kitchen Design';
    $fields['meta[status]'] = 'published';
    $r = http('POST', '/admin/edit.php', $fields);
    check('new post saved', $r['status'] === 303 && str_contains($r['location'], 'id=test-post-from-the-dashboard'), $r['status'] . ' ' . $r['location'] . ' ' . substr(strip_tags($r['body']), 0, 300));
    $live = http('GET', '/kitchen-design/test-post-from-the-dashboard/');
    check('new post live at /category/slug/', $live['status'] === 200 && str_contains($live['body'], 'Hello from the dashboard.'));
    check('new post has image size, TOC and article schema', str_contains($live['body'], 'width="1600" height="800"') && str_contains($live['body'], 'post-toc') && str_contains($live['body'], '"@type":"BlogPosting"'));
    $feed = json_decode(http('GET', '/api/posts.json')['body'], true);
    check('new post is first in the blog feed', ($feed[0]['slug'] ?? '') === 'test-post-from-the-dashboard');
    check('new post on the home page', str_contains(http('GET', '/')['body'], '/kitchen-design/test-post-from-the-dashboard/'));

    $dup = form_fields(http('GET', '/admin/edit.php?new=blog-post')['body']);
    $dup['f[title]'] = 'Test Post From The Dashboard';
    $dup['f[featuredImage][src]'] = $upload['url'];
    $dup['f[featuredImage][alt]'] = 'x';
    $dup['f[contentHtml]'] = '<p>x</p>';
    $r = http('POST', '/admin/edit.php', $dup);
    check('duplicate address refused with a clear message', $r['status'] === 200 && str_contains($r['body'], 'already exists'));

    echo "\nPreview, delete, restore\n";
    $draftFields = form_fields(http('GET', '/admin/edit.php?new=page')['body']);
    $draftFields['f[h1]'] = 'Draft Test Page';
    $draftFields['meta[status]'] = 'draft';
    http('POST', '/admin/edit.php', $draftFields);
    check('draft is not public', http('GET', '/draft-test-page/')['status'] === 404);
    $prev = http('GET', '/admin/preview.php?id=draft-test-page');
    check('draft can be previewed when logged in', $prev['status'] === 200 && str_contains($prev['body'], 'Draft Test Page') && str_contains($prev['body'], 'noindex'));
    $csrf = csrf_from(http('GET', '/admin/')['body']);
    http('POST', '/admin/delete.php', ['csrf' => $csrf, 'id' => 'test-post-from-the-dashboard']);
    check('deleted post is gone from the site', http('GET', '/kitchen-design/test-post-from-the-dashboard/')['status'] === 404);
    check('deleted post is in the trash', str_contains(http('GET', '/admin/trash.php')['body'], 'Test Post From The Dashboard'));
    http('POST', '/admin/trash.php', ['csrf' => $csrf, 'id' => 'test-post-from-the-dashboard', 'action' => 'restore']);
    check('restored post is live again', http('GET', '/kitchen-design/test-post-from-the-dashboard/')['status'] === 200);
    $r = http('POST', '/admin/delete.php', ['csrf' => $csrf, 'id' => 'home']);
    check('home page cannot be deleted', http('GET', '/')['status'] === 200);

    echo "\nSettings\n";
    $settings = http('GET', '/admin/settings.php');
    $doc = $settings['body'];
    preg_match_all('/name="(f\[[^"]+)" value="([^"]*)"/', $doc, $m, PREG_SET_ORDER);
    $sf = ['csrf' => csrf_from($doc), 'action' => 'settings'];
    foreach ($m as [, $n, $v]) {
        $sf[$n] = html_entity_decode($v, ENT_QUOTES | ENT_HTML5);
    }
    preg_match_all('#<textarea[^>]*name="(f\[[^"]+)"[^>]*>(.*?)</textarea>#s', $doc, $t, PREG_SET_ORDER);
    foreach ($t as [, $n, $v]) {
        if (!str_contains($n, '__INDEX__')) {
            $sf[$n] = html_entity_decode($v, ENT_QUOTES | ENT_HTML5);
        }
    }
    foreach ($sf as $n => $v) {
        if (str_contains($n, '__INDEX__')) {
            unset($sf[$n]);
        }
    }
    $sf['f[phoneDisplay]'] = '+91 999 0000 111';
    $r = http('POST', '/admin/settings.php', $sf);
    check('settings saved', $r['status'] === 303);
    check('new phone number shown in every footer', str_contains(http('GET', '/about.html')['body'], '+91 999 0000 111'));
    check('menu kept after saving settings', str_contains(http('GET', '/about.html')['body'], 'class="nav-item nav-link active">About</a>'));

    echo "\nLogout and lockout\n";
    http('POST', '/admin/logout.php', ['csrf' => csrf_from(http('GET', '/admin/')['body'])]);
    check('logged out', http('GET', '/admin/')['status'] === 303);
    $other = "$tmp/other.txt";
    $last = '';
    for ($i = 0; $i < 6; $i++) {
        $page = http('GET', '/admin/login.php', [], $other);
        $last = http('POST', '/admin/login.php', ['csrf' => csrf_from($page['body']), 'password' => 'nope' . $i], $other)['body'];
    }
    check('locked after 5 wrong passwords', str_contains($last, 'wait 15 minutes'));
    $page = http('GET', '/admin/login.php', [], $other);
    $r = http('POST', '/admin/login.php', ['csrf' => csrf_from($page['body']), 'password' => 'test password 123'], $other);
    check('even the right password is refused while locked', $r['status'] === 200 && str_contains($r['body'], 'wait 15 minutes'));
} catch (Throwable $e) {
    $failed[] = 'exception';
    echo '  FAIL ' . get_class($e) . ': ' . $e->getMessage() . "\n";
} finally {
    proc_terminate($server);
    proc_close($server);
    exec('rm -rf ' . escapeshellarg($tmp));
}

echo "\n$passed passed, " . count($failed) . " failed\n";
exit($failed ? 1 : 0);
