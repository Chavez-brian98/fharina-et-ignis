<?php

/**
 * Etiquetas de las acciones de bitácora (compartido por la vista de Bitácora y
 * por el detalle de caja, que muestra la línea de tiempo de una caja).
 *
 * Uso: require_once __DIR__ . '/partials/audit_acciones.php'; y luego
 * $meta = auditActionMeta($action, $actionMeta);
 */

$actionMeta = [
    'login'        => ['label' => 'Inicio de sesión', 'color' => 'bg-blue-50 text-blue-600', 'icon' => 'fa-right-to-bracket'],
    'login_failed' => ['label' => 'Acceso fallido', 'color' => 'bg-red-50 text-red-600', 'icon' => 'fa-triangle-exclamation'],
    'logout'       => ['label' => 'Cierre de sesión', 'color' => 'bg-gray-100 text-gray-500', 'icon' => 'fa-right-from-bracket'],
    'create'       => ['label' => 'Creación', 'color' => 'bg-green-50 text-green-600', 'icon' => 'fa-plus'],
    'update'       => ['label' => 'Modificación', 'color' => 'bg-amber-50 text-amber-600', 'icon' => 'fa-pen'],
    'toggle'       => ['label' => 'Cambio de estado', 'color' => 'bg-violet-50 text-violet-600', 'icon' => 'fa-toggle-on'],
    'delete'       => ['label' => 'Eliminación', 'color' => 'bg-red-50 text-red-600', 'icon' => 'fa-trash-can'],
    'sale'         => ['label' => 'Venta', 'color' => 'bg-teal-50 text-teal-600', 'icon' => 'fa-cart-shopping'],
    'receive'      => ['label' => 'Recepción de mercancía', 'color' => 'bg-green-50 text-green-600', 'icon' => 'fa-box-open'],
    'cancel'       => ['label' => 'Cancelación', 'color' => 'bg-gray-100 text-gray-500', 'icon' => 'fa-ban'],
    'cash_open'         => ['label' => 'Apertura de caja', 'color' => 'bg-green-50 text-green-600', 'icon' => 'fa-lock-open'],
    'cash_open_assign'  => ['label' => 'Caja asignada', 'color' => 'bg-green-50 text-green-600', 'icon' => 'fa-user-plus'],
    'cash_open_denied'  => ['label' => 'Apertura denegada', 'color' => 'bg-red-50 text-red-600', 'icon' => 'fa-user-lock'],
    'cash_close'        => ['label' => 'Cierre de caja', 'color' => 'bg-amber-50 text-amber-600', 'icon' => 'fa-lock'],
    'cash_reopen'       => ['label' => 'Reapertura de caja', 'color' => 'bg-violet-50 text-violet-600', 'icon' => 'fa-rotate-right'],
    'cash_movement'     => ['label' => 'Movimiento de efectivo', 'color' => 'bg-gray-100 text-gray-500', 'icon' => 'fa-money-bill-transfer'],
    'kiosk_open'   => ['label' => 'Quiosco desbloqueado', 'color' => 'bg-green-50 text-green-600', 'icon' => 'fa-lock-open'],
    'kiosk_close'  => ['label' => 'Quiosco bloqueado', 'color' => 'bg-gray-50 text-gray-500', 'icon' => 'fa-lock'],
    'kiosk_qr'     => ['label' => 'Marcación por QR (quiosco)', 'color' => 'bg-sky-50 text-sky-600', 'icon' => 'fa-qrcode'],
    'kiosk_rostro' => ['label' => 'Marcación por rostro (quiosco)', 'color' => 'bg-violet-50 text-violet-600', 'icon' => 'fa-face-smile'],
    'bulk'        => ['label' => 'Asignación masiva de turnos', 'color' => 'bg-sky-50 text-sky-600', 'icon' => 'fa-layer-group'],
    'face_enroll' => ['label' => 'Biometría enrolada', 'color' => 'bg-violet-50 text-violet-600', 'icon' => 'fa-face-smile'],
    'face_delete' => ['label' => 'Biometría eliminada', 'color' => 'bg-gray-100 text-gray-500', 'icon' => 'fa-eraser'],
];

if (!function_exists('auditActionMeta')) {
    function auditActionMeta($action, $meta)
    {
        return $meta[$action] ?? ['label' => $action, 'color' => 'bg-orange-50 text-orange-600', 'icon' => 'fa-circle-info'];
    }
}