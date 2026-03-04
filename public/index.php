<?php
/**
 * Public entry point
 * Web root je /srv/app/public/, ale aplikace je v /srv/app/
 */

// ROOT je rodičovská složka (public/..)
define('ROOT', dirname(__DIR__));

// Načti hlavní index ze složky výš
require ROOT . '/index.php';
