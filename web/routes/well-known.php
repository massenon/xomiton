<?php

declare(strict_types=1);

use App\Http\Controllers\Web\UniversalLinksController;
use Illuminate\Support\Facades\Route;

/*
 * Fichiers de liens universels (lot IOS-09), sans groupe `web` : voir bootstrap/app.php. Chemins sans extension pour
 * l'AASA ; HTTPS imposé par .htaccess (sauf /.well-known pour le TLS) ; aucune redirection, aucun cookie.
 */
Route::get('/.well-known/apple-app-site-association', [UniversalLinksController::class, 'apple'])->name('well-known.aasa');
Route::get('/.well-known/assetlinks.json', [UniversalLinksController::class, 'android'])->name('well-known.assetlinks');
