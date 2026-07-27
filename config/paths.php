<?php

function detectAppUrl(): string {
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $appRoot = realpath(dirname(__DIR__));

    if (!$docRoot || !$appRoot) {
        return '';
    }

    $docRoot = str_replace('\\', '/', $docRoot);
    $appRoot = str_replace('\\', '/', $appRoot);

    if (!str_starts_with($appRoot, $docRoot)) {
        return '';
    }

    $relative = substr($appRoot, strlen($docRoot));
    $relative = '/' . ltrim(str_replace('\\', '/', $relative), '/');

    return rtrim($relative, '/') === '' ? '' : rtrim($relative, '/');
}
