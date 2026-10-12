<?php
/** @var Router $router */
declare(strict_types=1);

$r = $router;

// ziyaretçi
$r->get('', [SiteController::class, 'home']);
$r->get('ara', [SiteController::class, 'search']);
$r->get('kategoriler', [SiteController::class, 'categories']);
$r->get('api/oneri', [SiteController::class, 'suggestApi']);
$r->get('api/secenekler/{id}', [SiteController::class, 'optionsApi']);
$r->get('robots.txt', [SiteController::class, 'robots']);
$r->get('sitemap.xml', [SiteController::class, 'sitemap']);
$r->get('favicon.svg', [SiteController::class, 'favicon']);

// hesap
$r->any('giris', [AccountController::class, 'login']);
$r->any('kayit', [AccountController::class, 'register']);
$r->post('cikis', [AccountController::class, 'logout']);
$r->any('sifre/{token}', [AccountController::class, 'reset']);
$r->get('favoriler', [AccountController::class, 'favorites']);
$r->post('favori/{id}', [AccountController::class, 'toggleFavorite']);

// panel
$r->get('panel', static fn() => Response::redirect(url(Auth::is('editor') || !Auth::user() ? 'panel/ogeler' : ''), 302));
$r->get('panel/ogeler', [PanelItemsController::class, 'index']);
$r->any('panel/oge/yeni', [PanelItemsController::class, 'create']);
$r->any('panel/oge/{id}', [PanelItemsController::class, 'edit']);
$r->post('panel/oge/{id}/sil', [PanelItemsController::class, 'delete']);
$r->post('panel/oge/{id}/gorsel', [PanelItemsController::class, 'upload']);
$r->post('panel/oge/{id}/gorsel-sira', [PanelItemsController::class, 'reorder']);
$r->post('panel/gorsel/{id}/sil', [PanelItemsController::class, 'deleteImage']);
$r->post('panel/gorsel/{id}/kapak', [PanelItemsController::class, 'coverImage']);
$r->get('panel/oge/{id}/gecmis', [PanelItemsController::class, 'history']);

$r->get('panel/kategoriler', [PanelCategoriesController::class, 'index']);
$r->any('panel/kategori/yeni', [PanelCategoriesController::class, 'create']);
$r->any('panel/kategori/{id}', [PanelCategoriesController::class, 'edit']);
$r->post('panel/kategori/{id}/sil', [PanelCategoriesController::class, 'delete']);

$r->get('panel/oneriler', [PanelReviewController::class, 'index']);
$r->post('panel/oneri/{id}', [PanelReviewController::class, 'decide']);

$r->any('panel/degerler', [PanelDataController::class, 'values']);
$r->any('panel/kullanicilar', [PanelDataController::class, 'users']);
$r->any('panel/ayarlar', [PanelDataController::class, 'settings']);
$r->post('panel/bakim/{task}', [PanelDataController::class, 'maintenance']);
$r->get('panel/yedek', [PanelDataController::class, 'backup']);

// kategori ve öğe sayfaları (en sonda)
$r->any('{cat}/oner', [ProposeController::class, 'create']);
$r->get('{cat}', [SiteController::class, 'listing']);
$r->get('{cat}/{item}', [SiteController::class, 'detail']);
$r->any('{cat}/{item}/duzelt', [ProposeController::class, 'edit']);
