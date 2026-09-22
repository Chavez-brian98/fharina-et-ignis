<?php

/**
 * Router: resuelve la URL entrante a contrato/acción/id + si es pública.
 */
class Router
{
    private $controllers;
    private $siteActions;
    private $publicRoutes;

    public function __construct(array $routes)
    {
        $this->controllers = $routes['controllers'] ?? [];
        $this->siteActions = $routes['site_actions'] ?? [];
        $this->publicRoutes = array_merge(array_keys($this->siteActions), ['auth']);
    }

    /**
     * Traduce una URL ("products", "categorias/edit/3", "producto/42", ...) en
     * un array: ['controller' => ?, 'action' => ?, 'id' => ?, 'public' => bool].
     * 'controller' es null cuando la ruta no existe (→ 404).
     */
    public function resolve($url)
    {
        $segments = $url !== '' ? explode('/', $url) : [];
        $key = strtolower($segments[0] ?? 'home');
        $action = $segments[1] ?? 'index';
        $id = isset($segments[2]) ? (int) $segments[2] : null;

        // URLs públicas del sitio (español → método del SiteController)
        if (isset($this->siteActions[$key])) {
            if ($key === 'producto') {
                $action = 'product';
                $id = isset($segments[1]) ? (int) $segments[1] : null;
            } elseif (!isset($segments[1])) {
                $action = $this->siteActions[$key];
            }
        }

        return [
            'controller' => $this->controllers[$key] ?? null,
            'action' => $action,
            'id' => $id,
            'public' => in_array($key, $this->publicRoutes, true),
        ];
    }
}