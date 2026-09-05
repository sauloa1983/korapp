<?php
$target = __DIR__ . '/../storage/app/public';
$link = __DIR__ . '/storage';

if (file_exists($link) || is_link($link)) {
    echo 'Ya existe public/storage';
    exit;
}

if (!is_dir($target)) {
    mkdir($target, 0755, true);
}

if (symlink($target, $link)) {
    echo 'OK: storage link creado. BORRA este archivo ahora.';
} else {
    echo 'Error: el hosting no permite symlinks. Pide a soporte el enlace.';
}
