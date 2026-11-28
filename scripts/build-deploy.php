<?php

declare(strict_types=1);

/*
 * Builds the upload-ready deploy package (spec 16.1): dist/htdocs/ and
 * dist/rentora-v{version}.zip. Run locally with `composer deploy:build`.
 *
 * Layout (the contents of dist/htdocs go straight into the host's document root):
 *
 *   htdocs/index.php, .htaccess, build/, uploads/, icons/, sw.js ...   the contents of public/
 *   htdocs/_rentora/                                                    everything else, "Require all denied"
 *
 * Code folders must not sit at the top level: Apache applies a folder's
 * .htaccess access rule before any rewrite runs, so a real folder named like
 * a route prefix (app/, vendor/ ...) would answer 403 instead of the route.
 * checkRoutePrefixes() fails the build if that ever happens again.
 *
 * Never caches config, routes or views: cached absolute paths would break
 * on the server.
 */

$root = dirname(__DIR__);
$dist = $root.DIRECTORY_SEPARATOR.'dist';
$target = $dist.DIRECTORY_SEPARATOR.'htdocs';
$appDir = $target.DIRECTORY_SEPARATOR.'_rentora';
$version = trim((string) @file_get_contents($root.'/VERSION')) ?: '0.0.0';

/** First URL segments used by routes. A top-level folder with one of these names would shadow the route. */
const ROUTE_PREFIXES = ['app', 'portal', 'install', 'system', 'files', 'r', 'invite', 'vendor', 'pay', 'webhooks', 'listings', '.well-known'];

function step(string $message): void
{
    fwrite(STDOUT, "\n==> {$message}\n");
}

function fail(string $message): never
{
    fwrite(STDERR, "\nBUILD FAILED: {$message}\n");
    exit(1);
}

function run(string $command, string $cwd): void
{
    fwrite(STDOUT, "    $ {$command}\n");
    $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $cwd);
    if (! is_resource($process) || proc_close($process) !== 0) {
        fail("command failed: {$command}");
    }
}

function removeTree(string $path): void
{
    if (! file_exists($path) && ! is_link($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        @chmod($path, 0666);
        unlink($path);

        return;
    }
    foreach (scandir($path) ?: [] as $item) {
        if ($item !== '.' && $item !== '..') {
            removeTree($path.DIRECTORY_SEPARATOR.$item);
        }
    }
    rmdir($path);
}

/** Paths (relative to the project root, forward slashes) left out of the package. */
function excluded(string $relative): bool
{
    $relative = str_replace('\\', '/', $relative);
    $topLevel = ['.git', '.github', 'node_modules', 'tests', 'dist', 'docs', 'vendor', 'android', 'android-app', 'deploy', 'scripts', '.env', '.phpunit.cache',
        'phpunit.xml', '.phpunit.result.cache', 'build-info.json', '.editorconfig', '.idea', '.vscode', 'database/database.sqlite'];
    foreach ($topLevel as $prefix) {
        if ($relative === $prefix || str_starts_with($relative, $prefix.'/')) {
            return true;
        }
    }
    if (preg_match('#^\.env\.#', $relative) === 1) {
        return true;
    }
    // Markdown files except README.md.
    if (str_ends_with(strtolower($relative), '.md') && $relative !== 'README.md') {
        return true;
    }
    // Runtime state: keep the folders (and their .gitignore), drop the contents.
    foreach (['storage/logs/', 'storage/framework/cache/', 'storage/framework/sessions/', 'storage/framework/views/', 'storage/framework/testing/', 'storage/app/private/', 'public/uploads/'] as $dir) {
        if (str_starts_with($relative, $dir) && basename($relative) !== '.gitignore' && basename($relative) !== '.gitkeep') {
            return true;
        }
    }

    return in_array($relative, ['storage/app/installed.lock', 'public/hot', 'public/storage', 'public/index.php'], true);
}

/** Copies $from into $to, skipping excluded() paths (judged relative to $base) and any top-level names in $skip. */
function copyTree(string $from, string $to, string $base, int &$count, array $skip = []): void
{
    if (! is_dir($to)) {
        mkdir($to, 0755, true);
    }
    foreach (scandir($from) ?: [] as $item) {
        if ($item === '.' || $item === '..' || in_array($item, $skip, true)) {
            continue;
        }
        $source = $from.DIRECTORY_SEPARATOR.$item;
        $relative = ltrim(substr($source, strlen($base)), '\\/');
        if (excluded($relative)) {
            continue;
        }
        $destination = $to.DIRECTORY_SEPARATOR.$item;
        if (is_dir($source)) {
            copyTree($source, $destination, $base, $count);
        } else {
            copy($source, $destination);
            $count++;
        }
    }
}

function countFiles(string $path): int
{
    $count = 0;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $count++;
        }
    }

    return $count;
}

function gitCommit(string $root): string
{
    $hash = trim((string) @shell_exec('git -C '.escapeshellarg($root).' rev-parse --short=7 HEAD'));

    return preg_match('/^[0-9a-f]{7,}$/', $hash) === 1 ? $hash : 'unknown';
}

/**
 * The first URL segment of every registered route, read from the app itself,
 * plus the fixed list above (so the check still works if artisan fails).
 *
 * @return list<string>
 */
function routePrefixes(string $root): array
{
    $prefixes = ROUTE_PREFIXES;
    $json = @shell_exec('php '.escapeshellarg($root.'/artisan').' route:list --json 2>'.(PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null'));
    $routes = is_string($json) ? json_decode($json, true) : null;
    if (is_array($routes)) {
        foreach ($routes as $route) {
            $segment = explode('/', trim((string) ($route['uri'] ?? ''), '/'))[0];
            if ($segment !== '' && ! str_starts_with($segment, '{')) {
                $prefixes[] = $segment;
            }
        }
    } else {
        fwrite(STDOUT, "    (route:list unavailable, using the fixed prefix list only)\n");
    }

    return array_values(array_unique(array_map('strtolower', $prefixes)));
}

/** Fails the build if a top-level folder of the document root has the same name as a route prefix. */
function checkRoutePrefixes(string $target, array $prefixes): void
{
    $clashes = [];
    foreach (scandir($target) ?: [] as $item) {
        if ($item !== '.' && $item !== '..' && is_dir($target.DIRECTORY_SEPARATOR.$item) && in_array(strtolower($item), $prefixes, true)) {
            $clashes[] = $item.'/';
        }
    }
    if ($clashes !== []) {
        fail('top-level folder(s) '.implode(', ', $clashes).' match a route prefix. Apache would answer 403 for those routes. Move them under _rentora/.');
    }
    fwrite(STDOUT, '    OK: no top-level folder matches a route prefix ('.count($prefixes)." prefixes checked)\n");
}

step("Building Rentora v{$version}");

step('Frontend assets (npm ci, npm run build)');
run('npm ci --no-audit --no-fund', $root);
run('npm run build', $root);

step('Copying application into dist/htdocs/_rentora');
removeTree($target);
removeTree($dist.DIRECTORY_SEPARATOR.'rentora'); // the pre-1.0.1 layout
@mkdir($dist, 0755, true);
$copied = 0;
copyTree($root, $appDir, $root, $copied, ['public']);
fwrite(STDOUT, "    {$copied} files copied\n");

step('Copying public/ into dist/htdocs');
$copied = 0;
copyTree($root.DIRECTORY_SEPARATOR.'public', $target, $root, $copied);
fwrite(STDOUT, "    {$copied} files copied\n");

step('Installing production Composer dependencies');
run('composer install --no-dev --optimize-autoloader --no-interaction --no-progress', $appDir);

step('Web server files');
// Front controller pointing at _rentora/, and the standard Laravel public .htaccess (copied with public/).
copy($root.'/deploy/htdocs-index.php', $target.'/index.php');
if (! is_file($target.'/.htaccess')) {
    fail('public/.htaccess is missing.');
}
file_put_contents($appDir.'/.htaccess', "Require all denied\n");
copy($root.'/deploy/.env.production.example', $appDir.'/.env.production.example');

step('build-info.json');
$info = [
    'version' => $version,
    'commit' => gitCommit($root),
    'built_at' => gmdate('Y-m-d\TH:i:s\Z'),
];
file_put_contents($appDir.'/build-info.json', json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
fwrite(STDOUT, '    '.json_encode($info)."\n");

step('Checking top-level folders against route prefixes');
checkRoutePrefixes($target, routePrefixes($root));

step('Zipping');
$zipPath = $dist.DIRECTORY_SEPARATOR."rentora-v{$version}.zip";
@unlink($zipPath);
$zip = new ZipArchive;
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fail("cannot create {$zipPath}");
}
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
foreach ($iterator as $file) {
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($target) + 1));
    $file->isDir() ? $zip->addEmptyDir($relative) : $zip->addFile($file->getPathname(), $relative);
}
$zip->close();

$files = countFiles($target);
fwrite(STDOUT, "\nDone: {$zipPath}\n{$files} files in dist/htdocs (upload its contents into the host's htdocs/).\n");
if ($files > 25000) {
    fwrite(STDOUT, "WARNING: more than 25,000 files. Some free hosts limit the number of files (inodes).\n");
}
fwrite(STDOUT, "Not cached on purpose: config, routes and views (see docs/DEPLOYMENT.md).\n");
