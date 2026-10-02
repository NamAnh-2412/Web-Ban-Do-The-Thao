@php
    $fieldId = $id;
    $fieldName = $name;
    $autocomplete = $autocomplete ?? 'current-password';
    $isRequired = $required ?? true;
    $minlength = $minlength ?? null;
@endphp
<div class="password-field">
    <input
        class="form-control @error($fieldName) is-invalid @enderror"
        type="password"
        id="{{ $fieldId }}"
        name="{{ $fieldName }}"
        autocomplete="{{ $autocomplete }}"
        @if ($isRequired) required @endif
        @if ($minlength) minlength="{{ $minlength }}" @endif
    >
    <button
        type="button"
        class="password-toggle"
        data-password-toggle
        aria-controls="{{ $fieldId }}"
        aria-label="Hiện mật khẩu"
        aria-pressed="false"
    >
        <svg class="icon-show" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
            <path fill="none" stroke="currentColor" stroke-width="2" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/>
            <circle fill="none" stroke="currentColor" stroke-width="2" cx="12" cy="12" r="3"/>
        </svg>
        <svg class="icon-hide" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
            <path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M3 3l18 18"/>
            <path fill="none" stroke="currentColor" stroke-width="2" d="M10.6 10.6a3 3 0 0 0 4.2 4.2M9.9 5.1A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a18.5 18.5 0 0 1-3.2 4.4M6.7 6.7C3.8 8.3 2 12 2 12a18.8 18.8 0 0 0 5.4 6.2 11 11 0 0 0 6.2 1.7c1.2 0 2.3-.2 3.3-.5"/>
        </svg>
    </button>
</div>
@error($fieldName)
    <div class="invalid-feedback d-block">{{ $message }}</div>
@enderror
