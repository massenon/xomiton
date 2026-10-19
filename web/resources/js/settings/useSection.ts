// Enregistrement d'une section de /parametres (P8-09) : état « en cours », erreurs de champ (422 → fieldErrors, sans
// toast : le formulaire les montre près des champs et le résumé reste à l'écran), toast de succès, toast d'erreur pour
// le reste (les appels de settings/api.ts sont `silent`). Chaque section garde son propre état : une erreur dans
// l'une ne bloque pas les autres.
import { ref } from 'vue';
import { ApiError, fieldErrors } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { t } from '@/app/i18n';

export function useSection() {
    const toast = useToast();
    const saving = ref(false);
    const errors = ref<Record<string, string>>({});

    /** Lance l'action ; vrai si elle a abouti (l'action range elle-même le résultat). */
    async function run(action: () => Promise<unknown>, success: string | null = null): Promise<boolean> {
        if (saving.value) return false;
        saving.value = true;
        errors.value = {};
        try {
            await action();
            if (success) toast.success(success);
            return true;
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) {
                errors.value = fieldErrors(error);
            } else {
                toast.error(error instanceof Error && error.message ? error.message : String(t('microcopy.error.unreachable')));
            }
            return false;
        } finally {
            saving.value = false;
        }
    }

    return { saving, errors, run };
}
