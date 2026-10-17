<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
 * API /api/v1 (SOFTWARE_ARCHITECTURE.md §4) : REST JSON, Sanctum, réponses { data, meta, links }, erreurs { message, errors }.
 * Les familles de routes sont ajoutées phase par phase (P5 alertes, P6 fondation et me/, P7 feed, P8 communauté, P9 pair et sync).
 */

Route::get('/health', static fn () => response()->json(['data' => ['status' => 'ok', 'version' => config('xomiton.version', '1.0')]]))
    ->name('api.health');
