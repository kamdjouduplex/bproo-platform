{{-- Shared teacher form. $showUserPicker / $showLock / $showActive optional. --}}
@if(!empty($showUserPicker))
    <div class="form-span-2">
        <label class="label">Utilisateur @if(!empty($userRequired)) <span style="color:#b91c1c;">*</span> @endif</label>
        <select class="input" wire:model.live="userId">
            <option value="">{{ !empty($userRequired) ? 'Choisir un utilisateur…' : '— Aucun compte lié —' }}</option>
            @foreach($availableUsers as $u)
                <option value="{{ $u->id }}">
                    {{ $u->name }}
                    @if($u->phone) — {{ $u->phone }} @endif
                    @if($u->email) — {{ $u->email }} @endif
                </option>
            @endforeach
        </select>
        @error('userId') <span class="text-error">{{ $message }}</span> @enderror
        @if($availableUsers->isEmpty())
            <p style="margin:4px 0 0; font-size:12px; color:#b45309;">Aucun utilisateur libre. Créez d’abord le compte dans Utilisateurs.</p>
        @endif
    </div>
@endif

@include('school::livewire.partials.student-photo-field', [
    'photoFile' => $photoFile ?? null,
    'photoPath' => $photoPath ?? null,
    'photoUrl' => $photoUrl ?? null,
    'wireModel' => 'photoFile',
    'removeModel' => 'removePhoto',
])

<div>
    <label class="label">ID enseignant</label>
    <input class="input" wire:model="teacherCode" type="text">
    @error('teacherCode') <span class="text-error">{{ $message }}</span> @enderror
</div>
<div>
    <label class="label">Sexe</label>
    <select class="input" wire:model="gender">
        <option value="">—</option>
        @foreach($genders as $opt)
            <option value="{{ $opt->value }}">{{ $opt->label }}</option>
        @endforeach
    </select>
    @error('gender') <span class="text-error">{{ $message }}</span> @enderror
</div>
<div>
    <label class="label">Nom</label>
    <input class="input" wire:model="lastName" type="text">
    @error('lastName') <span class="text-error">{{ $message }}</span> @enderror
</div>
<div>
    <label class="label">Prénom</label>
    <input class="input" wire:model="firstName" type="text">
    @error('firstName') <span class="text-error">{{ $message }}</span> @enderror
</div>
<div>
    <label class="label">Téléphone <span style="color:#b91c1c;">*</span></label>
    <input class="input" wire:model="phone" type="text">
    @error('phone') <span class="text-error">{{ $message }}</span> @enderror
</div>
<div>
    <label class="label">Email <span style="font-weight:400; color:#94a3b8;">(facultatif)</span></label>
    <input class="input" wire:model="email" type="email">
    @error('email') <span class="text-error">{{ $message }}</span> @enderror
</div>
<div class="form-span-2">
    <label class="label">Adresse</label>
    <textarea class="input" rows="2" wire:model="address"></textarea>
    @error('address') <span class="text-error">{{ $message }}</span> @enderror
</div>
<div>
    <label class="label">Niveau d’étude</label>
    <select class="input" wire:model="educationLevel">
        <option value="">—</option>
        @foreach($educationLevels as $opt)
            <option value="{{ $opt->value }}">{{ $opt->label }}</option>
        @endforeach
    </select>
    @error('educationLevel') <span class="text-error">{{ $message }}</span> @enderror
</div>
<div>
    <label class="label">Diplôme</label>
    <select class="input" wire:model.live="diplomaKind">
        <option value="">—</option>
        @foreach($diplomaKinds as $opt)
            <option value="{{ $opt->value }}">{{ $opt->label }}</option>
        @endforeach
    </select>
    @error('diplomaKind') <span class="text-error">{{ $message }}</span> @enderror
</div>
<div class="form-span-2">
    <label class="label">Intitulé (si Autre)</label>
    <input class="input" wire:model="diplomaLabel" type="text" placeholder="Précisez le diplôme">
    @error('diplomaLabel') <span class="text-error">{{ $message }}</span> @enderror
</div>
<div class="form-span-2">
    <label class="label">Étude en cours</label>
    <input class="input" wire:model="studiesInProgress" type="text" placeholder="Laisser vide si aucune">
    @error('studiesInProgress') <span class="text-error">{{ $message }}</span> @enderror
</div>
<div class="form-span-2">
    <label class="label">Matières enseignées</label>
    <div style="display:flex; flex-wrap:wrap; gap:8px 16px; padding:8px 0;">
        @forelse($subjects as $subject)
            <label style="display:inline-flex; align-items:center; gap:6px; font-size:13px;">
                <input type="checkbox" wire:model="subjectIds" value="{{ $subject->id }}">
                {{ $subject->name }}
            </label>
        @empty
            <span style="font-size:13px; color:#64748b;">Aucune matière dans le référentiel.</span>
        @endforelse
    </div>
    @error('subjectIds') <span class="text-error">{{ $message }}</span> @enderror
    @error('subjectIds.*') <span class="text-error">{{ $message }}</span> @enderror
</div>
<div>
    <label class="label">Section d’enseignement</label>
    <select class="input" wire:model="teachingSection">
        <option value="">—</option>
        @foreach($teachingSections as $opt)
            <option value="{{ $opt->value }}">{{ $opt->label }}</option>
        @endforeach
    </select>
    @error('teachingSection') <span class="text-error">{{ $message }}</span> @enderror
</div>
<div>
    <label class="label">Horaire</label>
    <input class="input" wire:model="scheduleNote" type="text" placeholder="Ex. Temps plein, Lun–Ven 7h30–15h40">
    @error('scheduleNote') <span class="text-error">{{ $message }}</span> @enderror
</div>
@if(!empty($showLock))
    <div class="form-span-2">
        <label class="label" style="margin:0;">
            <input type="checkbox" wire:model.live="lockOnSave"> Valider le dossier (l’enseignant ne pourra plus le modifier)
        </label>
    </div>
@endif
@if(!empty($showActive))
    <div class="form-span-2">
        <label class="label" style="margin:0;">
            <input type="checkbox" wire:model="isActive"> Dossier actif
        </label>
    </div>
@endif
