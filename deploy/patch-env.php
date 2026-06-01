<?php

$options = getopt('', [
    'app-url:',
    'db-host:',
    'db-port:',
    'db-database:',
    'db-user:',
    'db-password:',
]);

$envPath = dirname(__DIR__).'/.env';

if (! is_file($envPath)) {
    fwrite(STDERR, ".env not found at {$envPath}\n");
    exit(1);
}

$contents = file_get_contents($envPath);

$replacements = [
    'APP_NAME' => 'TenderAI',
    'APP_ENV' => 'production',
    'APP_DEBUG' => 'false',
    'APP_URL' => $options['app-url'] ?? 'https://ahmadalhashaykeh.com/tenderai',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => $options['db-host'] ?? '127.0.0.1',
    'DB_PORT' => $options['db-port'] ?? '3306',
    'DB_DATABASE' => $options['db-database'] ?? '',
    'DB_USERNAME' => $options['db-user'] ?? '',
    'DB_PASSWORD' => $options['db-password'] ?? '',
    'QUEUE_CONNECTION' => 'database',
];

foreach ($replacements as $key => $value) {
    $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
    $line = $key.'='.$value;

    if (preg_match($pattern, $contents)) {
        $contents = preg_replace($pattern, $line, $contents);
    } else {
        $contents .= PHP_EOL.$line;
    }
}

file_put_contents($envPath, $contents);

echo "Updated .env for production.\n";
