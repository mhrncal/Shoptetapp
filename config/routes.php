<?php
/**
 * Definice rout aplikace
 * Format: ['METHOD', '/path', 'Controller@method', middleware[]]
 */
return [

    // ---- Auth -----------------------------------------------
    ['GET',  '/login',    'AuthController@loginForm',  []],
    ['POST', '/login',    'AuthController@login',       []],
    ['GET',  '/logout',   'AuthController@logout',      ['auth']],
    ['GET',  '/register', 'AuthController@registerForm',[]],
    ['POST', '/register', 'AuthController@register',    []],
    ['GET',  '/pending',  'AuthController@pending',     ['auth']],

    // ---- Dashboard ------------------------------------------
    ['GET', '/',          'DashboardController@index',  ['auth', 'approved']],
    ['GET', '/dashboard', 'DashboardController@index',  ['auth', 'approved']],

    // ---- Profil ---------------------------------------------
    ['GET',  '/profile',  'ProfileController@edit',    ['auth', 'approved']],
    ['POST', '/profile',  'ProfileController@update',  ['auth', 'approved']],

    // ---- Nastavení ------------------------------------------
    ['GET',  '/settings', 'SettingsController@index',  ['auth', 'approved', 'module:settings']],
    ['POST', '/settings', 'SettingsController@update', ['auth', 'approved', 'module:settings']],

    // ---- Statistiky -----------------------------------------
    ['GET', '/statistics', 'StatisticsController@index', ['auth', 'approved', 'module:statistics']],

    // ---- Produkty -------------------------------------------
    ['GET', '/products',          'ProductController@index',  ['auth', 'approved']],
    ['GET', '/products/{id}',     'ProductController@detail', ['auth', 'approved']],

    // ---- FAQ ------------------------------------------------
    ['GET',    '/faq',       'FaqController@index',   ['auth', 'approved', 'module:faq']],
    ['POST',   '/faq',       'FaqController@store',   ['auth', 'approved', 'module:faq']],
    ['POST',   '/faq/{id}',  'FaqController@update',  ['auth', 'approved', 'module:faq']],
    ['DELETE', '/faq/{id}',  'FaqController@delete',  ['auth', 'approved', 'module:faq']],

    // ---- Pobočky --------------------------------------------
    ['GET',    '/branches',        'BranchController@index',   ['auth', 'approved', 'module:branches']],
    ['POST',   '/branches',        'BranchController@store',   ['auth', 'approved', 'module:branches']],
    ['GET',    '/branches/{id}',   'BranchController@edit',    ['auth', 'approved', 'module:branches']],
    ['POST',   '/branches/{id}',   'BranchController@update',  ['auth', 'approved', 'module:branches']],
    ['DELETE', '/branches/{id}',   'BranchController@delete',  ['auth', 'approved', 'module:branches']],

    // ---- Události -------------------------------------------
    ['GET',    '/events',       'EventController@index',   ['auth', 'approved', 'module:event_calendar']],
    ['POST',   '/events',       'EventController@store',   ['auth', 'approved', 'module:event_calendar']],
    ['POST',   '/events/{id}',  'EventController@update',  ['auth', 'approved', 'module:event_calendar']],
    ['DELETE', '/events/{id}',  'EventController@delete',  ['auth', 'approved', 'module:event_calendar']],

    // ---- XML Import -----------------------------------------
    ['GET',  '/xml',         'XmlController@index',   ['auth', 'approved', 'module:xml_import']],
    ['POST', '/xml/start',   'XmlController@start',   ['auth', 'approved', 'module:xml_import']],
    ['GET',  '/xml/status',  'XmlController@status',  ['auth', 'approved', 'module:xml_import']],

    // ---- API Tokeny -----------------------------------------
    ['GET',    '/api-tokens',        'ApiTokenController@index',   ['auth', 'approved', 'module:api_access']],
    ['POST',   '/api-tokens',        'ApiTokenController@store',   ['auth', 'approved', 'module:api_access']],
    ['DELETE', '/api-tokens/{id}',   'ApiTokenController@delete',  ['auth', 'approved', 'module:api_access']],

    // ---- Webhooky -------------------------------------------
    ['GET',    '/webhooks',       'WebhookController@index',   ['auth', 'approved', 'module:webhooks']],
    ['POST',   '/webhooks',       'WebhookController@store',   ['auth', 'approved', 'module:webhooks']],
    ['POST',   '/webhooks/{id}',  'WebhookController@update',  ['auth', 'approved', 'module:webhooks']],
    ['DELETE', '/webhooks/{id}',  'WebhookController@delete',  ['auth', 'approved', 'module:webhooks']],

    // ---- ADMIN ----------------------------------------------
    ['GET',  '/admin',              'Admin\AdminController@dashboard',   ['auth', 'approved', 'superadmin']],

    // Uživatelé
    ['GET',  '/admin/users',              'Admin\UserController@index',    ['auth', 'approved', 'superadmin']],
    ['GET',  '/admin/users/{id}',         'Admin\UserController@detail',   ['auth', 'approved', 'superadmin']],
    ['GET',  '/admin/users/{id}/edit',    'Admin\UserController@edit',     ['auth', 'approved', 'superadmin']],
    ['POST', '/admin/users/{id}/edit',    'Admin\UserController@update',   ['auth', 'approved', 'superadmin']],
    ['POST', '/admin/users/{id}/approve', 'Admin\UserController@approve',  ['auth', 'approved', 'superadmin']],
    ['POST', '/admin/users/{id}/reject',  'Admin\UserController@reject',   ['auth', 'approved', 'superadmin']],
    ['POST', '/admin/users/{id}/delete',  'Admin\UserController@delete',   ['auth', 'approved', 'superadmin']],
    ['POST', '/admin/users/{id}/impersonate', 'Admin\UserController@impersonate', ['auth', 'approved', 'superadmin']],
    ['POST', '/admin/impersonate/stop',   'Admin\UserController@stopImpersonate', ['auth']],

    // Moduly
    ['GET',  '/admin/modules',            'Admin\ModuleController@index',  ['auth', 'approved', 'superadmin']],
    ['POST', '/admin/modules/assign',     'Admin\ModuleController@assign', ['auth', 'approved', 'superadmin']],

    // XML fronta
    ['GET',  '/admin/xml-queue',          'Admin\XmlQueueController@index', ['auth', 'approved', 'superadmin']],

    // Systém
    ['GET',  '/admin/system',             'Admin\SystemController@index',   ['auth', 'approved', 'superadmin']],
    ['GET',  '/admin/audit-log',          'Admin\SystemController@auditLog',['auth', 'approved', 'superadmin']],
];
