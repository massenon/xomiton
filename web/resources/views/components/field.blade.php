{{--
    Champ de formulaire (prototype : .mb-3 > label.form-label + .form-control / .form-select + .form-text + .invalid-feedback ;
    interrupteur .form-check.form-switch[role=switch] ; COMPOSANTS §Champs, ACCESSIBILITE §4).
    Props :
      name, label   : obligatoires (libellé visible au-dessus, lié par for/id ; id = f-{name} par défaut)
      type          : text | email | tel | password | date | number | url | search | textarea | select | checkbox | switch | radio-group | checkbox-group
      hint          : aide sous le champ (liée par aria-describedby)
      error         : message d'erreur explicite (sinon $errors->first(name)) → aria-invalid, texte rouge + icône, lien depuis <x-error-summary>
      optional      : ajoute « (facultatif) » au libellé (jamais d'astérisque seul)
      options       : select et groupes : [valeur => libellé]
      value         : valeur initiale (old() prime) ; tableau pour checkbox-group
      autocomplete, inputmode, maxlength, placeholder, rows : passés au champ ; maxlength sur textarea affiche un compteur
    Hauteur 44 px et focus 3 px : social-xomiton.css. Mot de passe : bouton œil (collage autorisé).
--}}
@props([
    'name',
    'label',
    'type' => 'text',
    'hint' => null,
    'error' => null,
    'optional' => false,
    'options' => [],
    'value' => null,
    'autocomplete' => null,
    'inputmode' => null,
    'maxlength' => null,
    'placeholder' => null,
    'rows' => 4,
])
@php
    $id = $attributes->get('id', 'f-'.str_replace(['[', ']', '.'], '-', $name));
    $error ??= isset($errors) ? $errors->first($name) : null;
    $describedBy = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
    $val = old($name, $value);
    // Booléen normalisé par une Form Request (prepareForValidation) puis flashé : comparé comme '1' / '0'.
    $val = is_bool($val) ? ($val ? '1' : '0') : $val;
    $isGroup = in_array($type, ['radio-group', 'checkbox-group'], true);
    $common = array_filter([
        'id' => $id, 'name' => $name, 'aria-describedby' => $describedBy ?: null, 'aria-invalid' => $error ? 'true' : null,
        'autocomplete' => $autocomplete, 'inputmode' => $inputmode, 'maxlength' => $maxlength, 'placeholder' => $placeholder,
    ], fn ($v) => $v !== null);
    $optionalMark = $optional ? ' <span class="small text-secondary fw-normal">'.e(__('components.field.optional')).'</span>' : '';
@endphp
<div class="mb-3 xm-field" @if ($type === 'password') x-data="{ show: false }" @elseif ($type === 'textarea' && $maxlength) x-data="{ count: {{ mb_strlen((string) $val) }} }" @endif>
    @if (in_array($type, ['checkbox', 'switch'], true))
        <div class="form-check {{ $type === 'switch' ? 'form-switch' : '' }}">
            <input {{ $attributes->except('id')->merge($common)->class(['form-check-input', 'is-invalid' => $error]) }} type="checkbox" value="1" @if ($type === 'switch') role="switch" @endif @checked($val)>
            <label class="form-check-label" for="{{ $id }}">{{ $label }}{!! $optionalMark !!}</label>
        </div>
    @elseif ($isGroup)
        <fieldset id="{{ $id }}" tabindex="-1" @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif @if ($error) aria-invalid="true" @endif>
            <legend class="form-label fs-6">{{ $label }}{!! $optionalMark !!}</legend>
            @foreach ($options as $k => $l)
                @php($checked = $type === 'checkbox-group' ? in_array((string) $k, array_map('strval', (array) $val), true) : (string) $val === (string) $k)
                <div class="form-check">
                    <input class="form-check-input {{ $error ? 'is-invalid' : '' }}" type="{{ $type === 'radio-group' ? 'radio' : 'checkbox' }}" name="{{ $name }}{{ $type === 'checkbox-group' ? '[]' : '' }}" id="{{ $id }}-{{ $loop->index }}" value="{{ $k }}" @checked($checked)>
                    <label class="form-check-label" for="{{ $id }}-{{ $loop->index }}">{{ $l }}</label>
                </div>
            @endforeach
        </fieldset>
    @else
        <label class="form-label" for="{{ $id }}">{{ $label }}{!! $optionalMark !!}</label>
        @if ($type === 'textarea')
            <textarea {{ $attributes->except('id')->merge($common)->class(['form-control', 'is-invalid' => $error]) }} rows="{{ $rows }}" @if ($maxlength) x-on:input="count = $event.target.value.length" @endif>{{ $val }}</textarea>
            @if ($maxlength)<div class="form-text text-end" id="{{ $id }}-count" x-text="count + ' / ' + {{ (int) $maxlength }}">{{ __('components.field.counter', ['count' => mb_strlen((string) $val), 'max' => $maxlength]) }}</div>@endif
        @elseif ($type === 'select')
            <select {{ $attributes->except('id')->merge($common)->class(['form-select', 'is-invalid' => $error]) }}>
                @foreach ($options as $k => $l)
                    <option value="{{ $k }}" @selected((string) $val === (string) $k)>{{ $l }}</option>
                @endforeach
            </select>
        @elseif ($type === 'password')
            <div class="input-group">
                <input {{ $attributes->except('id')->merge($common)->class(['form-control', 'is-invalid' => $error]) }} type="password" x-bind:type="show ? 'text' : 'password'">
                <button class="btn btn-light border" type="button" x-on:click="show = ! show" x-bind:aria-pressed="show ? 'true' : 'false'" aria-pressed="false" x-bind:aria-label="show ? @js(__('components.field.hide_password')) : @js(__('components.field.show_password'))" aria-label="{{ __('components.field.show_password') }}">
                    <i class="bi bi-eye" x-bind:class="show ? 'bi-eye-slash' : 'bi-eye'" aria-hidden="true"></i>
                </button>
            </div>
        @else
            <input {{ $attributes->except('id')->merge($common)->class(['form-control', 'is-invalid' => $error]) }} type="{{ $type }}" value="{{ $val }}">
        @endif
    @endif
    @if ($hint)<div id="{{ $id }}-hint" class="form-text">{{ $hint }}</div>@endif
    @if ($error)<div id="{{ $id }}-error" class="invalid-feedback d-block"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ $error }}</div>@endif
</div>
