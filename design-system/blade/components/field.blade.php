@props([
    'name',
    'label',
    'type' => 'text',        // text|email|tel|password|date|number|textarea|select|checkbox|switch
    'hint' => null,
    'optional' => false,
    'options' => [],         // select : [valeur => libellé]
    'value' => null,
    'autocomplete' => null,
    'inputmode' => null,
    'maxlength' => null,
])
@php
    $id = $attributes->get('id', 'f-'.str_replace(['[', ']', '.'], '-', $name));
    $error = $errors->first($name);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
    $val = old($name, $value);
    $common = ['id' => $id, 'name' => $name, 'aria-describedby' => $describedBy ?: null, 'aria-invalid' => $error ? 'true' : null, 'autocomplete' => $autocomplete, 'inputmode' => $inputmode, 'maxlength' => $maxlength];
@endphp
<div class="mb-4" @if ($type === 'password') x-data="{ show: false }" @endif>
    @if (in_array($type, ['checkbox', 'switch']))
        <div class="form-check {{ $type === 'switch' ? 'form-switch' : '' }}">
            <input {{ $attributes->except('id')->merge($common)->class(['form-check-input', 'is-invalid' => $error]) }} type="checkbox" value="1" @checked($val)>
            <label class="form-check-label" for="{{ $id }}">{{ $label }}@if ($optional) <span class="xm-hint">({{ __('facultatif') }})</span>@endif</label>
        </div>
    @else
        <label class="form-label" for="{{ $id }}">{{ $label }}@if ($optional) <span class="xm-hint fw-normal">({{ __('facultatif') }})</span>@endif</label>
        @if ($type === 'textarea')
            <textarea {{ $attributes->except('id')->merge($common)->class(['form-control', 'is-invalid' => $error]) }} rows="4">{{ $val }}</textarea>
        @elseif ($type === 'select')
            <select {{ $attributes->except('id')->merge($common)->class(['form-select', 'is-invalid' => $error]) }}>
                @foreach ($options as $k => $l)
                    <option value="{{ $k }}" @selected((string) $val === (string) $k)>{{ $l }}</option>
                @endforeach
            </select>
        @elseif ($type === 'password')
            <div class="input-group">
                <input {{ $attributes->except('id')->merge($common)->class(['form-control', 'is-invalid' => $error]) }} x-bind:type="show ? 'text' : 'password'" type="password">
                <button class="btn btn-light border" type="button" @click="show = !show" x-bind:aria-pressed="show" aria-label="{{ __('Afficher le mot de passe') }}">
                    <i class="bi" x-bind:class="show ? 'bi-eye-slash' : 'bi-eye'" aria-hidden="true"></i>
                </button>
            </div>
        @else
            <input {{ $attributes->except('id')->merge($common)->class(['form-control', 'is-invalid' => $error]) }} type="{{ $type }}" value="{{ $val }}">
        @endif
    @endif
    @if ($hint)<div id="{{ $id }}-hint" class="form-text">{{ $hint }}</div>@endif
    @if ($error)<div id="{{ $id }}-error" class="invalid-feedback d-block"><i class="bi bi-exclamation-circle" aria-hidden="true"></i> {{ $error }}</div>@endif
</div>
