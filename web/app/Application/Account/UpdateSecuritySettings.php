<?php

declare(strict_types=1);

namespace App\Application\Account;

use App\Models\Profile;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * PATCH me/security (P6-06, prototype `simulateur.html` l.1260) : mode discret (`profiles.discreet_mode`), aperçus
 * masqués (`user_settings.mask_sensitive_notifications`), verrouillage par code (`pin_enabled`) et titre d'onglet neutre.
 *
 * Q37 b : le code PIN reste sur l'appareil (stockage sécurisé) ; le serveur ne connaît que `pin_enabled` et efface tout
 * `pin_hash` hérité. Activer le mode discret masque aussi les aperçus et neutralise le titre d'onglet, sauf si la même
 * requête dit le contraire (texte d'aide `profile.settings.security.discreet_mode_help`) ; le désactiver ne touche à rien
 * d'autre. Journal `me.security_updated` avec la liste des champs, jamais leurs valeurs.
 */
final class UpdateSecuritySettings
{
    /**
     * @param  array{discreet?: bool, mask_previews?: bool, pin_enabled?: bool, neutral_tab_title?: bool}  $data
     */
    public function __invoke(User $user, array $data): User
    {
        $touched = [];

        DB::transaction(function () use ($user, $data, &$touched): void {
            $profile = $user->profile ?? Profile::query()->create(['user_id' => $user->getKey()]);
            $settings = $user->settings ?? UserSettings::ensure($user);

            if (array_key_exists('discreet', $data)) {
                $profile->discreet_mode = $data['discreet'];
                $touched[] = 'discreet';

                if ($data['discreet']) {
                    $data += ['mask_previews' => true, 'neutral_tab_title' => true];
                }
            }

            if (array_key_exists('mask_previews', $data)) {
                $settings->mask_sensitive_notifications = $data['mask_previews'];
                $touched[] = 'mask_previews';
            }

            if (array_key_exists('neutral_tab_title', $data)) {
                $settings->neutral_tab_title = $data['neutral_tab_title'];
                $touched[] = 'neutral_tab_title';
            }

            if (array_key_exists('pin_enabled', $data)) {
                $settings->pin_enabled = $data['pin_enabled'];
                $touched[] = 'pin_enabled';
            }

            // Q37 b : aucun code PIN côté serveur.
            $settings->pin_hash = null;

            $profile->save();
            $settings->save();

            $user->setRelation('profile', $profile);
            $user->setRelation('settings', $settings);
        });

        if ($touched !== []) {
            Audit::log('me.security_updated', $user, 'champs='.implode(',', array_unique($touched)), $user);
        }

        return $user;
    }
}
