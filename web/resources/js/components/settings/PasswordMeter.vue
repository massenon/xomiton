<script setup lang="ts">
// Jauge de solidité du mot de passe dans l'espace Vue (P8-09) : même HTML et mêmes règles que
// resources/views/auth/partials/password-meter.blade.php + resources/js/public/password-meter.js (fonctions `checks`
// et `score` réutilisées, mêmes règles que App\Domain\Auth\PasswordStrength). 4 segments + texte + règles cochées
// (couleur jamais seule) ; seul le niveau est en aria-live, et son texte ne change qu'avec le niveau. Indicative : le
// serveur tranche. Props : id (les règles sont `{id}-rules`, à passer en aria-describedby du champ), password.
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { LEVELS, SEGMENTS, checks, score } from '@/public/password-meter.js';

const props = defineProps<{ id: string; password: string }>();

const RULES = ['length', 'uppercase', 'lowercase', 'digit', 'symbol'] as const;

const { t } = useI18n();

const empty = computed(() => props.password.length === 0);
const level = computed(() => score(props.password) as keyof typeof LEVELS);
const shown = computed(() => (empty.value ? 'empty' : level.value));
const results = computed(() => checks(props.password) as Record<string, boolean>);
const lit = computed(() => (empty.value ? 0 : LEVELS[level.value].segments));
const tone = computed(() => LEVELS[level.value].tone);
</script>

<template>
    <div :id="id" class="xm-pw-meter mt-2" :data-level="shown">
        <div class="xm-pw-meter__segments" aria-hidden="true">
            <span v-for="i in SEGMENTS" :key="i" class="xm-pw-meter__segment" :class="i <= lit ? ['is-on', `is-${tone}`] : []"></span>
        </div>
        <p class="xm-pw-meter__label small fw-bold mb-1" aria-live="polite" aria-atomic="true"><span class="visually-hidden">{{ t('auth.password_meter_label') }} </span><span data-password-label>{{ t(`auth.password_strength.${shown}`) }}</span></p>
        <ul :id="`${id}-rules`" class="xm-pw-meter__rules list-unstyled small mb-0">
            <li v-for="rule in RULES" :key="rule" :data-rule="rule" :data-checked="results[rule] ? 'true' : 'false'" :class="{ 'is-ok': results[rule] }"><i class="bi me-1" :class="results[rule] ? 'bi-check-circle-fill' : 'bi-circle'" data-rule-icon aria-hidden="true"></i>{{ t(`auth.password_rules.${rule}`) }}</li>
        </ul>
    </div>
</template>
