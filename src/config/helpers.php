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
 * Sube varias imágenes desde un input múltiple (name="campo[]") a public/uploads/.
 * Devuelve un array de URLs (/uploads/nombre.ext), un array vacío si no se envió
 * ningún archivo y false si algún archivo falló (flash de error ya registrado).
 */
function upload_gallery($field)
{
    if (empty($_FILES[$field])) {
        return [];
    }

    $files = $_FILES[$field];

    if (!is_array($files['name'])) {
        $files = [
            'name' => [$files['name']],
            'type' => [$files['type']],
            'tmp_name' => [$files['tmp_name']],
            'error' => [$files['error']],
            'size' => [$files['size']],
        ];
    }

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $uploadDir = __DIR__ . '/../public/uploads/';
    $uploaded = [];

    foreach ($files['name'] as $i => $name) {
        if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            flash('error', 'Hubo un problema al subir una de las fotos de la galería.');
            return false;
        }

        $info = file_exists($files['tmp_name'][$i]) ? @getimagesize($files['tmp_name'][$i]) : false;

        if ($info === false || !in_array($info['mime'], $allowedMimes, true)) {
            flash('error', 'Formato de imagen no válido. Usa JPG, PNG, WEBP o GIF.');
            return false;
        }

        if ($files['size'][$i] > 2 * 1024 * 1024) {
            flash('error', 'Una de las fotos supera el tamaño máximo de 2 MB.');
            return false;
        }

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $filename = 'gallery_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest = $uploadDir . $filename;

        if (!move_uploaded_file($files['tmp_name'][$i], $dest)) {
            flash('error', 'No se pudo guardar una de las fotos. Revisa los permisos de la carpeta uploads.');
            return false;
        }

        $uploaded[] = '/uploads/' . $filename;
    }

    return $uploaded;
}

/**
 * Valida un DUI salvadoreño: 8 dígitos, guion, 1 dígito (00000000-0).
 */
function validar_dui($dui)
{
    return is_string($dui) && preg_match('/^\d{8}-\d$/', $dui) === 1;
}

/**
 * Devuelve la edad en años a partir de una fecha (Y-m-d o datetime).
 * Devuelve null si la fecha es inválida o está vacía.
 */
function edadDesde($fecha)
{
    if (empty($fecha)) {
        return null;
    }
    try {
        $nacimiento = new DateTime($fecha);
        return (new DateTime('today'))->diff($nacimiento)->y;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * ¿El usuario en sesión tiene permiso de esa acción en ese módulo?
 * Ej.: puede('products', 'edit'), puede('pos')
 */
function puede($module, $action = 'view')
{
    return Permiso::puede($module, $action);
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