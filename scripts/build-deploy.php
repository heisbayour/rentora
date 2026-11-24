<?php

declare(strict_types=1);

/*
 * Builds the upload-ready deploy package (spec 16.1): dist/rentora/ and
 * dist/rentora-v{version}.zip. Run locally with `composer deploy:build`.
 * Never caches config, routes or views: cached absolute paths would break
 * on the server.
 */

$root = dirname(__DIR__);
$dist = $root.DIRECTORY_SEPARATOR.'dist';
$target = $dist.DIRECTORY_SEPARATOR.'rentora';
$version = trim((string) @file_get_contents($root.'/VERSION')) ?: '0.0.0';

function step(string $message): void
{
    fwrite(STDOUT, "\n==> {$message}\n");
}

function run(string $command, string $cwd): void
{
    fwrite(STDOUT, "    $ {$command}\n");
    $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $cwd);
    if (! is_resource($process) || proc_close($process) !== 0) {
        fwrite(STDERR, "Command failed: {$command}\n");
        exit(1);
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

/** Paths (relative, forward slashes) left out of the package. */
function excluded(string $relative): bool
{
    $relative = str_replace('\\', '/', $relative);
    $topLevel = ['.git', '.github', 'node_modules', 'tests', 'dist', 'docs', 'vendor', 'android', '.env', '.phpunit.cache',
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

    return $relative === 'storage/app/installed.lock' || $relative === 'public/hot';
}

function copyTree(string $from, string $to, string $base, int &$count): void
{
    if (! is_dir($to)) {
        mkdir($to, 0755, true);
    }
    foreach (scandir($from) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
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

step("Building Rentora v{$version}");

step('Frontend assets (npm ci, npm run build)');
run('npm ci --no-audit --no-fund', $root);
run('npm run build', $root);

step('Copying project into dist/rentora');
removeTree($target);
@mkdir($dist, 0755, true);
$copied = 0;
copyTree($root, $target, $root, $copied);
fwrite(STDOUT, "    {$copied} files copied\n");

step('Installing production Composer dependencies');
run('composer install --no-dev --optimize-autoloader --no-interaction --no-progress', $target);

step('Web server files');
copy($root.'/deploy/root.htaccess', $target.'/.htaccess');
copy($root.'/deploy/.env.production.example', $target.'/.env.production.example');
foreach (['app', 'bootstrap', 'config', 'database', 'lang', 'resources', 'routes', 'storage', 'vendor', 'scripts', 'deploy'] as $dir) {
    if (is_dir($target.'/'.$dir)) {
        file_put_contents($target.'/'.$dir.'/.htaccess', "Require all denied\n");
    }
}

step('build-info.json');
$info = [
    'version' => $version,
    'commit' => gitCommit($root),
    'built_at' => gmdate('Y-m-d\TH:i:s\Z'),
];
file_put_contents($target.'/build-info.json', json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
fwrite(STDOUT, '    '.json_encode($info)."\n");

step('Zipping');
$zipPath = $dist.DIRECTORY_SEPARATOR."rentora-v{$version}.zip";
@unlink($zipPath);
$zip = new ZipArchive;
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Cannot create {$zipPath}\n");
    exit(1);
}
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
foreach ($iterator as $file) {
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($target) + 1));
    $file->isDir() ? $zip->addEmptyDir($relative) : $zip->addFile($file->getPathname(), $relative);
}
$zip->close();

$files = countFiles($target);
fwrite(STDOUT, "\nDone: {$zipPath}\n{$files} files in dist/rentora.\n");
if ($files > 25000) {
    fwrite(STDOUT, "WARNING: more than 25,000 files. Some free hosts limit the number of files (inodes).\n");
}
fwrite(STDOUT, "Not cached on purpose: config, routes and views (see docs/DEPLOYMENT.md).\n");
