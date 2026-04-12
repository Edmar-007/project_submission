<?php

function ensure_upload_dir(string $relativeDir): string {
    $path = APP_ROOT . '/' . trim($relativeDir, '/');
    if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create upload directory.');
    }
    return $path;
}

function detect_upload_extension(array $file, array $allowedMimeMap, array $fallbackExtensions = []): ?string {
    $tmp = (string) ($file['tmp_name'] ?? '');
    $mime = '';
    if ($tmp !== '' && is_file($tmp) && function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string) finfo_file($finfo, $tmp);
            finfo_close($finfo);
        }
    }
    if ($mime !== '' && isset($allowedMimeMap[$mime])) {
        return $allowedMimeMap[$mime];
    }
    $original = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (in_array($original, $fallbackExtensions, true)) {
        return $original === 'jpeg' ? 'jpg' : $original;
    }
    return null;
}

function store_uploaded_file(array $file, string $relativeDir, string $filenameBase, array $allowedMimeMap, int $maxBytes, array $fallbackExtensions = []): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => null, 'message' => ''];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'Upload failed.'];
    }
    if ((int) ($file['size'] ?? 0) > $maxBytes) {
        return ['ok' => false, 'message' => 'Uploaded file exceeds the allowed size.'];
    }
    $ext = detect_upload_extension($file, $allowedMimeMap, $fallbackExtensions);
    if (!$ext) {
        return ['ok' => false, 'message' => 'File type is not allowed.'];
    }
    $dir = ensure_upload_dir($relativeDir);
    $safeBase = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $filenameBase);
    $filename = trim((string) $safeBase, '-') . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destination = $dir . '/' . $filename;
    if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $destination)) {
        return ['ok' => false, 'message' => 'Unable to save the uploaded file.'];
    }
    return ['ok' => true, 'path' => trim($relativeDir, '/') . '/' . $filename, 'message' => '', 'filename' => $filename];
}
