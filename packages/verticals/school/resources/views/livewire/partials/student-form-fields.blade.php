                    <div class="form-grid">
                        <div>
                            <label class="label">Matricule interne</label>
                            <input class="input" wire:model="studentCode" type="text" placeholder="SCH-2026-0001">
                            @error('studentCode') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="label">NISU (ministère)</label>
                            <input class="input" wire:model="nisu" type="text" placeholder="Numéro d’identification scolaire unique">
                            @error('nisu') <span class="text-error">{{ $message }}</span> @enderror
                            <p style="margin:4px 0 0; font-size:11px; color:#64748b;">Attribué par le ministère — distinct du matricule de l’école.</p>
                        </div>
                        <div>
                            <label class="label">Genre</label>
                            <select class="input" wire:model="gender">
                                <option value="">—</option>
                                @foreach ($genders as $opt)
                                    <option value="{{ $opt->value }}">{{ $opt->label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label">Prénom</label>
                            <input class="input" wire:model="firstName" type="text">
                            @error('firstName') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="label">Nom</label>
                            <input class="input" wire:model="lastName" type="text">
                            @error('lastName') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="label">Date de naissance</label>
                            <input class="input" wire:model="birthDate" type="date">
                        </div>
                        <div>
                            <label class="label">Lieu de naissance</label>
                            <input class="input" wire:model="birthPlace" type="text">
                        </div>
                        <div class="form-span-2">
                            <label class="label">Adresse</label>
                            <input class="input" wire:model="address" type="text">
                        </div>
                        <div>
                            <label class="label">Parent / Tuteur</label>
                            <input class="input" wire:model="parentFullName" type="text">
                        </div>
                        <div>
                            <label class="label">Lien</label>
                            <select class="input" wire:model="parentRelationship">
                                <option value="">—</option>
                                @foreach (($relationships ?? []) as $rel)
                                    <option value="{{ $rel->value }}">{{ $rel->label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label">Téléphone parent</label>
                            <input class="input" wire:model="parentPhone" type="text">
                        </div>
                        <div>
                            <label class="label">Email parent</label>
                            <input class="input" wire:model="parentEmail" type="email">
                            @error('parentEmail') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="label">Contact d’urgence</label>
                            <input class="input" wire:model="emergencyContactName" type="text">
                        </div>
                        <div>
                            <label class="label">Tél. urgence</label>
                            <input class="input" wire:model="emergencyContactPhone" type="text">
                        </div>
                        <div class="form-span-2">
                            <label class="label">Établissement précédent</label>
                            <input class="input" wire:model="previousSchool" type="text">
                        </div>
