<?php

$options = getopt('', ['base:']);
$base = $options['base'] ?? '/tenderai/public/';

$path = dirname(__DIR__).'/public/.htaccess';

if (! is_file($path)) {
    fwrite(STDERR, "public/.htaccess not found\n");
    exit(1);
}

$contents = file_get_contents($path);
$directive = 'RewriteBase '.$base;

if (str_contains($contents, 'RewriteBase ')) {
    $contents = preg_replace('/^\s*RewriteBase\s+.*$/m', '    '.$directive, $contents);
} else {
    $contents = preg_replace(
        '/(RewriteEngine On\s*\n)/',
        "$1\n    {$directive}\n",
        $contents,
        1,
        $count
    );

    if ($count === 0) {
        fwrite(STDERR, "Could not insert RewriteBase into public/.htaccess\n");
        exit(1);
    }
}

file_put_contents($path, $contents);

echo "Set {$directive} in public/.htaccess\n";
