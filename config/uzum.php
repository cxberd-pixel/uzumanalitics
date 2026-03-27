<?php

function loadEnvFileIfNeeded($envPath)
{
    if (!is_file($envPath) || !is_readable($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        $parts = explode('=', $line, 2);

        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);
        $value = trim($value, "\"'");

        if ($key === '' || getenv($key) !== false) {
            continue;
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

if (getenv('UZUM_TOKEN') === false || getenv('UZUM_TOKEN') === '') {
    loadEnvFileIfNeeded(__DIR__ . '/../.env');
}

return [
    'token' => getenv('UZUM_TOKEN') ?: null,
    'base_url' => rtrim(getenv('UZUM_BASE_URL') ?: 'https://seller.uzum.uz/api', '/'),
];
