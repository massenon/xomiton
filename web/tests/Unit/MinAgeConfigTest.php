<?php

declare(strict_types=1);

/* Décision client du 11/11/2026 (remplace Q68) : âge minimal d'inscription = 18 ans, source unique xomiton.accounts.min_age. */
it('fixe l’âge minimal d’inscription à 18 ans dans une seule configuration', function (): void {
    expect(config('xomiton.accounts.min_age'))->toBe(18);
});
