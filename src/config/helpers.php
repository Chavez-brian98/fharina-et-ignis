<?php

/**
 * Funciones globales usadas por vistas y controladores.
 */

/**
 * Escapa un valor para uso seguro en HTML.
 */
function esc($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Devuelve el valor de una configuración del sistema.
 */
function setting($key, $default = null)
{
    $settings = $GLOBALS['__settings'] ?? [];

    return isset($settings[$key]) && $settings[$key] !== '' ? $settings[$key] : $default;
}

/**
 * Sube una imagen desde el formulario a public/uploads/ y devuelve su URL
 * (/uploads/nombre.ext). Devuelve null si no se envió archivo y false si falló
 * (en ese caso ya se aseguró de registrar el flash de error).
 */
function upload_image($field)
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$field];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'Hubo un problema al subir la imagen.');
        return false;
    }

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $info = file_exists($file['tmp_name']) ? @getimagesize($file['tmp_name']) : false;

    if ($info === false || !in_array($info['mime'], $allowedMimes, true)) {
        flash('error', 'Formato de imagen no válido. Usa JPG, PNG, WEBP o GIF.');
        return false;
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        flash('error', 'La imagen supera el tamaño máximo de 2 MB.');
        return false;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $uploadDir = __DIR__ . '/../public/uploads/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $filename = $field . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        flash('error', 'No se pudo guardar la imagen. Revisa los permisos de la carpeta uploads.');
        return false;
    }

    return '/uploads/' . $filename;
}

/**
 * Genera una URL limpia a partir de una ruta interna: /products, /categories/edit/3
 */
function url($path = '')
{
    return '/' . ltrim($path, '/');
}

/**
 * Devuelve y limpia un mensaje flash (guardado en sesión).
 */
function flash($key, $message = null)
{
    if ($message !== null) {
        $_SESSION[$key] = $message;
        return null;
    }
    if (isset($_SESSION[$key])) {
        $value = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $value;
    }
    return null;
}