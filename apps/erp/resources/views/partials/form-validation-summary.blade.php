@if ($errors->any())
    <div
        class="form-validation-summary"
        role="alert"
        aria-live="polite"
        id="form-validation-summary"
        x-data
        x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'start' })"
    >
        <p class="form-validation-summary__title">
            <span aria-hidden="true">⚠</span>
            Veuillez corriger {{ $errors->count() === 1 ? 'l\'erreur suivante' : 'les ' . $errors->count() . ' erreurs suivantes' }} :
        </p>
        <ul class="form-validation-summary__list">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
