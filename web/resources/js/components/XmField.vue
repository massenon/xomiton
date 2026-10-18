<script setup lang="ts">
// Champ de formulaire : même HTML que <x-field> (.mb-3.xm-field > label.form-label + .form-control / .form-select +
// .form-text + .invalid-feedback ; interrupteur .form-check.form-switch[role=switch] ; COMPOSANTS §Champs,
// ACCESSIBILITE §4). v-model ; émet `blur` (validation à la sortie du champ, DEVELOPMENT_STANDARDS §4).
// name, label (obligatoires ; id = f-{name}, celui des liens de <XmErrorSummary>) ; type : text | email | tel | password
// | date | number | url | search | textarea | select | checkbox | switch | radio-group | checkbox-group ; hint (lié par
// aria-describedby) ; error (aria-invalid, texte + icône) ; optional (« (facultatif) », jamais d'astérisque seul) ;
// options ({ valeur: libellé }) ; autocomplete, inputmode, maxlength (compteur sur textarea), placeholder, rows.
import { computed, ref, useAttrs } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ inheritAttrs: false });

type Value = string | number | boolean | null | undefined | Array<string | number>;

const props = withDefaults(defineProps<{
    name: string;
    label: string;
    type?: string;
    hint?: string | null;
    error?: string | null;
    optional?: boolean;
    options?: Record<string, string>;
    modelValue?: Value;
    id?: string | null;
    autocomplete?: string | null;
    inputmode?: string | null;
    maxlength?: number | null;
    placeholder?: string | null;
    rows?: number;
}>(), { type: 'text', hint: null, error: null, optional: false, options: () => ({}), modelValue: null, id: null, autocomplete: null, inputmode: null, maxlength: null, placeholder: null, rows: 4 });

const emit = defineEmits<{ 'update:modelValue': [value: Value]; blur: [event: FocusEvent] }>();

const { t } = useI18n();
const attrs = useAttrs();
const show = ref(false);

const fieldId = computed(() => props.id ?? `f-${props.name.replace(/[[\].]/g, '-')}`);
const isGroup = computed(() => props.type === 'radio-group' || props.type === 'checkbox-group');
const isCheck = computed(() => props.type === 'checkbox' || props.type === 'switch');
const describedBy = computed(() => [
    props.hint ? `${fieldId.value}-hint` : null,
    typeof attrs['aria-describedby'] === 'string' ? attrs['aria-describedby'] : null,
    props.error ? `${fieldId.value}-error` : null,
].filter(Boolean).join(' ') || undefined);
const passthrough = computed(() => {
    const { id: _id, 'aria-describedby': _described, ...rest } = attrs as Record<string, unknown>;
    return rest;
});
const common = computed<Record<string, unknown>>(() => ({
    ...passthrough.value,
    id: fieldId.value,
    name: props.name,
    'aria-describedby': describedBy.value,
    'aria-invalid': props.error ? 'true' : undefined,
    autocomplete: props.autocomplete ?? undefined,
    inputmode: props.inputmode ?? undefined,
    maxlength: props.maxlength ?? undefined,
    placeholder: props.placeholder ?? undefined,
}));
const text = computed(() => (props.modelValue === null || props.modelValue === undefined ? '' : String(props.modelValue)));
const count = computed(() => Array.from(text.value).length);
const checkedValues = computed(() => (Array.isArray(props.modelValue) ? props.modelValue.map(String) : []));

const onInput = (event: Event) => emit('update:modelValue', (event.target as HTMLInputElement).value);
const onCheck = (event: Event) => emit('update:modelValue', (event.target as HTMLInputElement).checked);
const onGroup = (event: Event, key: string) => {
    const input = event.target as HTMLInputElement;
    if (props.type === 'radio-group') {
        emit('update:modelValue', key);
        return;
    }
    const next = new Set(checkedValues.value);
    if (input.checked) next.add(key);
    else next.delete(key);
    emit('update:modelValue', Object.keys(props.options).filter((k) => next.has(k)));
};
const onBlur = (event: FocusEvent) => emit('blur', event);
</script>

<template>
    <div class="mb-3 xm-field">
        <div v-if="isCheck" :class="['form-check', { 'form-switch': type === 'switch' }]">
            <input v-bind="common" :class="['form-check-input', { 'is-invalid': Boolean(error) }]" type="checkbox" value="1" :role="type === 'switch' ? 'switch' : undefined" :checked="modelValue === true || modelValue === '1' || modelValue === 1" @change="onCheck" @blur="onBlur">
            <label class="form-check-label" :for="fieldId">{{ label }}<template v-if="optional"> <span class="small text-secondary fw-normal">{{ t('components.field.optional') }}</span></template></label>
        </div>
        <fieldset v-else-if="isGroup" :id="fieldId" tabindex="-1" :aria-describedby="describedBy" :aria-invalid="error ? 'true' : undefined">
            <legend class="form-label fs-6">{{ label }}<template v-if="optional"> <span class="small text-secondary fw-normal">{{ t('components.field.optional') }}</span></template></legend>
            <div v-for="(optionLabel, key, index) in options" :key="key" class="form-check">
                <input :class="['form-check-input', { 'is-invalid': Boolean(error) }]" :type="type === 'radio-group' ? 'radio' : 'checkbox'" :name="type === 'checkbox-group' ? `${name}[]` : name" :id="`${fieldId}-${index}`" :value="key" :checked="type === 'checkbox-group' ? checkedValues.includes(String(key)) : text === String(key)" @change="onGroup($event, String(key))" @blur="onBlur">
                <label class="form-check-label" :for="`${fieldId}-${index}`">{{ optionLabel }}</label>
            </div>
        </fieldset>
        <template v-else>
            <label class="form-label" :for="fieldId">{{ label }}<template v-if="optional"> <span class="small text-secondary fw-normal">{{ t('components.field.optional') }}</span></template></label>
            <template v-if="type === 'textarea'">
                <textarea v-bind="common" :class="['form-control', { 'is-invalid': Boolean(error) }]" :rows="rows" :value="text" @input="onInput" @blur="onBlur"></textarea>
                <div v-if="maxlength" :id="`${fieldId}-count`" class="form-text text-end">{{ t('components.field.counter', { count, max: maxlength }) }}</div>
            </template>
            <select v-else-if="type === 'select'" v-bind="common" :class="['form-select', { 'is-invalid': Boolean(error) }]" :value="text" @change="onInput" @blur="onBlur">
                <option v-for="(optionLabel, key) in options" :key="key" :value="key" :selected="text === String(key)">{{ optionLabel }}</option>
            </select>
            <div v-else-if="type === 'password'" class="input-group">
                <input v-bind="common" :class="['form-control', { 'is-invalid': Boolean(error) }]" :type="show ? 'text' : 'password'" :value="text" @input="onInput" @blur="onBlur">
                <button class="btn btn-light border" type="button" :aria-pressed="show ? 'true' : 'false'" :aria-label="show ? t('components.field.hide_password') : t('components.field.show_password')" @click="show = !show">
                    <i :class="['bi', show ? 'bi-eye-slash' : 'bi-eye']" aria-hidden="true"></i>
                </button>
            </div>
            <input v-else v-bind="common" :class="['form-control', { 'is-invalid': Boolean(error) }]" :type="type" :value="text" @input="onInput" @blur="onBlur">
        </template>
        <div v-if="hint" :id="`${fieldId}-hint`" class="form-text">{{ hint }}</div>
        <div v-if="error" :id="`${fieldId}-error`" class="invalid-feedback d-block"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ error }}</div>
    </div>
</template>
