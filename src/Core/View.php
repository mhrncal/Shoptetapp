<?php

namespace ShopCode\Core;

class View
{
    private static string $viewsPath = '';

    public static function init(): void
    {
        self::$viewsPath = ROOT . '/src/Views/';
    }

    /**
     * Renderuje view uvnitř layoutu
     * @param string $view    Cesta k view relativně od Views/ (např. 'dashboard/index')
     * @param array  $data    Proměnné dostupné ve view
     * @param string $layout  Layout: 'main' | 'auth' | 'admin'
     */
    public static function render(string $view, array $data = [], string $layout = 'main'): void
    {
        // Dostupné proměnné ve view
        extract($data);
        $flash       = Session::getFlash();
        $csrfToken   = Session::getCsrfToken();
        $currentUser = Session::get('user');
        $currentPath = (new Request())->path;

        // Nejdřív zachytíme obsah view do bufferu
        ob_start();
        $viewFile = self::$viewsPath . $view . '.php';
        if (!file_exists($viewFile)) {
            ob_end_clean();
            die("View nenalezen: {$view}");
        }
        include $viewFile;
        $content = ob_get_clean();

        // Pak renderujeme layout (který vloží $content)
        $layoutFile = self::$viewsPath . 'layouts/' . $layout . '.php';
        if (!file_exists($layoutFile)) {
            die("Layout nenalezen: {$layout}");
        }
        include $layoutFile;
    }

    /**
     * Renderuje partial (bez layoutu)
     */
    public static function partial(string $view, array $data = []): string
    {
        extract($data);
        ob_start();
        include self::$viewsPath . $view . '.php';
        return ob_get_clean();
    }

    /**
     * Escape pro výstup do HTML
     */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
