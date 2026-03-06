<?php
/**
 * Definice rout aplikace
 * Format: ['METHOD', '/path', 'Controller@method', middleware[]]
 */
return [


    // Diagnostika
    ['GET', '/diag', 'DiagController@index', []],
    ['GET', '/diag/migrate', 'DiagController@runMigration', []],
    // ---- Auth -----------------------------------------------
    ['GET',  '/login',    'AuthController@loginForm',  []],
    ['POST', '/login',    'AuthController@login',       []],
    ['GET',  '/logout',               'AuthController@logout',                    ['auth']],
//     ['GET',  '/register',             'AuthController@registerForm',              []],
//     ['POST', '/register',             'AuthController@register',                  []],
    ['GET',  '/pending',  'AuthController@pending',     ['auth']],

    // ---- Dashboard ------------------------------------------
    ['GET', '/',          'DashboardController@index',  ['auth', 'approved']],
    ['GET', '/dashboard', 'DashboardController@index',  ['auth', 'approved']],

    // ---- Profil ---------------------------------------------
    ['GET',  '/profile',  'ProfileController@edit',    ['auth', 'approved']],
    ['POST', '/profile',  'ProfileController@update',  ['auth', 'approved']],

    // ---- Nastavení ------------------------------------------
    ['GET',  '/settings',          'SettingsController@index',         ['auth', 'approved', 'module:settings']],
    ['POST', '/settings/profile',  'SettingsController@updateProfile', ['auth', 'approved', 'module:settings']],
    ['POST', '/settings/password', 'SettingsController@updatePassword',['auth', 'approved', 'module:settings']],
    ['POST', '/settings/delete',   'SettingsController@deleteAccount', ['auth', 'approved', 'module:settings']],

    // ---- Statistiky -----------------------------------------
    ['GET', '/statistics', 'StatisticsController@index', ['auth', 'approved', 'module:statistics']],

    // ---- Produkty -------------------------------------------
    ['GET', '/products',          'ProductController@index',  ['auth', 'approved']],
    ['GET', '/product-videos',    'ProductTabController@videosIndex',  ['auth','approved','module:product_videos']],
    ['GET', '/product-tabs',      'ProductTabController@tabsIndex',    ['auth','approved','module:product_tabs']],
    ['GET', '/products/{id}',     'ProductController@detail', ['auth', 'approved']],

    // ---- FAQ ------------------------------------------------
    ['GET',    '/faq',       'FaqController@index',   ['auth', 'approved', 'module:faq']],
    ['POST',   '/faq',       'FaqController@store',   ['auth', 'approved', 'module:faq']],
    ['POST',   '/faq/{id}',  'FaqController@update',  ['auth', 'approved', 'module:faq']],
    ['DELETE', '/faq/{id}',  'FaqController@delete',  ['auth', 'approved', 'module:faq']],

    // ---- Pobočky --------------------------------------------
    ['GET',    '/branches',        'BranchController@index',   ['auth', 'approved', 'module:branches']],
    ['POST',   '/branches',        'BranchController@store',   ['auth', 'approved', 'module:branches']],
    ['GET',    '/branches/new',    'BranchController@edit',    ['auth', 'approved', 'module:branches']],
    ['GET',    '/branches/{id}',   'BranchController@edit',    ['auth', 'approved', 'module:branches']],
    ['POST',   '/branches/{id}',   'BranchController@update',  ['auth', 'approved', 'module:branches']],
    ['DELETE', '/branches/{id}',   'BranchController@delete',  ['auth', 'approved', 'module:branches']],

    // ---- Události -------------------------------------------
    ['GET',    '/events',       'EventController@index',   ['auth', 'approved', 'module:event_calendar']],
    ['POST',   '/events',       'EventController@store',   ['auth', 'approved', 'module:event_calendar']],
    ['POST',   '/events/{id}',  'EventController@update',  ['auth', 'approved', 'module:event_calendar']],
    ['DELETE', '/events/{id}',  'EventController@delete',  ['auth', 'approved', 'module:event_calendar']],

    // ---- XML Import -----------------------------------------

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
    ['GET',  '/admin/users/create',       'Admin\UserController@create',   ['auth', 'approved', 'superadmin']],
    ['POST', '/admin/users/create',       'Admin\UserController@store',    ['auth', 'approved', 'superadmin']],
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

    // ---- Shoptet Settings ----------------------------------
    ['GET',    '/settings/shoptet',        'ShoptetSettingsController@index',  ['auth','approved']],
    ['POST',   '/settings/shoptet',        'ShoptetSettingsController@store',  ['auth','approved']],
    ['POST',   '/settings/shoptet/delete', 'ShoptetSettingsController@delete', ['auth','approved']],

    // ============================================================
    // REST API v1 (Bearer token auth)
    // ============================================================
    ['GET', '/api/v1/products',      'ApiController@products', []],
    ['GET', '/products/search',         'ProductController@search', ['auth', 'approved']],
    ['GET', '/api/v1/products/{id}', 'ApiController@product',  []],
    ['GET', '/api/v1/faq',           'ApiController@faq',      []],

    ['GET', '/api/v1/branches',      'ApiController@branches', []],
    ['GET', '/api/v1/events',        'ApiController@events',   []],

    // ============================================================
    // Password reset
    // ============================================================
    ['GET',  '/password/reset',           'PasswordResetController@requestForm',  []],
    ['POST', '/password/reset',           'PasswordResetController@requestSubmit',[]],
    ['GET',  '/password/reset/{token}',   'PasswordResetController@resetForm',    []],
    ['POST', '/password/reset/{token}',   'PasswordResetController@resetSubmit',  []],

    // ============================================================
    // Fotorecenze
    // ============================================================
    ['GET',    '/reviews',                    'ReviewController@index',      ['auth','approved','module:reviews']],
    ['GET',    '/watermark/settings',         'WatermarkController@settings', ['auth','approved']],
    ['POST',   '/watermark/update',           'WatermarkController@update',   ['auth','approved']],
    ['POST',   '/watermark/regenerate',       'WatermarkController@regenerate', ['auth','approved']],
    ['POST',   '/reviews/bulk',               'ReviewController@bulkAction', ['auth','approved','module:reviews']],
    ['GET',    '/reviews/export/csv',          'ReviewController@exportCsv',  ['auth','approved','module:reviews']],
    ['GET',    '/reviews/export/xml',          'ReviewController@exportXml',  ['auth','approved','module:reviews']],
    ['GET',    '/reviews/{id}',               'ReviewController@detail',     ['auth','approved','module:reviews']],
    ['POST',   '/reviews/{id}/approve',       'ReviewController@approve',    ['auth','approved','module:reviews']],
    ['POST',   '/reviews/{id}/reject',        'ReviewController@reject',     ['auth','approved','module:reviews']],
    ['DELETE', '/reviews/{id}',               'ReviewController@delete',     ['auth','approved','module:reviews']],
    ['POST',   '/reviews/change-status',      'ReviewController@changeStatus', ['auth','approved','module:reviews']],
    ['POST',   '/reviews/update-note',       'ReviewController@updateNote',   ['auth','approved','module:reviews']],
    // Feeds
    ['GET',    '/feeds',                     'FeedController@index',          ['auth','approved']],
    ['GET',    '/feeds/create',              'FeedController@create',         ['auth','approved']],
    ['POST',   '/feeds/store',               'FeedController@store',          ['auth','approved']],
    ['POST',   '/feeds/sync',                'FeedController@sync',           ['auth','approved']],
    ['POST',   '/feeds/sync-background',     'FeedController@syncBackground', ['auth','approved']],
    ['POST',   '/feeds/delete',              'FeedController@delete',         ['auth','approved']],
    ['POST',   '/feeds/unlock-all',          'FeedController@unlockAll',      ['auth','approved']],
    ['GET',    '/feeds/sync-progress',       'FeedController@syncProgress',   ['auth','approved']],
    ['GET',    '/feeds/log/{id}',            'FeedController@downloadLog',    ['auth','approved']],
    ['POST',   '/reviews/delete',            'ReviewController@delete',       ['auth','approved','module:reviews']],
    ['POST',   '/reviews/download-zip',       'ReviewController@downloadZip',  ['auth','approved','module:reviews']],
    ['POST',   '/photo/delete',                'PhotoController@delete',        ['auth','approved']],
    ['POST',   '/photo/reupload',              'PhotoController@reupload',      ['auth','approved']],
    ['GET',    '/photo/download',              'PhotoController@download',      ['auth','approved']],

    // Product Tabs & Videos
    // ============================================================
    ['POST',   '/products/{product_id}/tabs',   'ProductTabController@tabStore',    ['auth','approved','module:product_tabs']],
    ['POST',   '/products/tabs/{id}',           'ProductTabController@tabUpdate',   ['auth','approved','module:product_tabs']],
    ['DELETE', '/products/tabs/{id}',           'ProductTabController@tabDelete',   ['auth','approved','module:product_tabs']],
    ['POST',   '/products/{product_id}/videos',        'ProductTabController@videoStore',  ['auth','approved','module:product_videos']],
    ['POST',   '/products/{product_id}/videos/upload',  'ProductTabController@videoUpload', ['auth','approved','module:product_videos']],
    ['DELETE', '/products/videos/{id}',         'ProductTabController@videoDelete',           ['auth','approved','module:product_videos']],
    ['POST',   '/products/videos/{id}/autoplay',  'ProductTabController@videoToggleAutoplay',    ['auth','approved','module:product_videos']],
    ['POST',   '/products/videos/{id}/delete',    'ProductTabController@videoDeleteFromIndex',   ['auth','approved','module:product_videos']],
];
