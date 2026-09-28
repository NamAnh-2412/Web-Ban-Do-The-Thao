<?php

$path = getenv('MYSQL_ATTR_SSL_CA');
if (! is_string($path) || $path === '' || ! is_readable($path)) {
    fwrite(STDERR, "MySQL CA file is missing or unreadable.\n");
    exit(1);
}

$pem = file_get_contents($path);
if (! is_string($pem) || ! str_contains($pem, 'BEGIN CERTIFICATE')) {
    fwrite(STDERR, "MySQL CA file is not a PEM certificate.\n");
    exit(1);
}

if (openssl_x509_read($pem) === false) {
    fwrite(STDERR, "MySQL CA file could not be parsed.\n");
    exit(1);
}

exit(0);
