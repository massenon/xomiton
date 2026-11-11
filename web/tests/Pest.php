<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
 * Configuration Pest (DEVELOPMENT_STANDARDS.md §10) : les tests Feature utilisent le TestCase Laravel
 * avec une base SQLite en mémoire rafraîchie ; les tests Unit du domaine n'ont pas besoin de base.
 */
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/**
 * Raccourci : une page publique doit répondre 200 avec un titre et une description.
 */
function expectPublicPage(string $uri): void
{
    test()->get($uri)
        ->assertOk()
        ->assertSee('<title>', false)
        ->assertSee('<meta name="description"', false);
}
