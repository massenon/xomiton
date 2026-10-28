<?php

declare(strict_types=1);

/* Q68 (2026-10-28) : âge minimal d'inscription, source unique xomiton.accounts.min_age. */
it('fixe l’âge minimal d’inscription à 15 ans dans une seule configuration', function (): void {
    expect(config('xomiton.accounts.min_age'))->toBe(15);
});
