<?php

namespace InovCom\Clients\Support;

use InovCom\Clients\Models\Client;
use InovCom\Clients\Models\Segment;
use Illuminate\Validation\Rule;

/**
 * Règles de validation centralisées (équivalent Form Request, compatible Livewire).
 * Partagées entre création et mise à jour pour garantir la cohérence.
 */
class ClientRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(?int $clientId, string $type): array
    {
        $rules = [
            'code' => ['required', 'string', 'max:100', Rule::unique(Client::class, 'code')->ignore($clientId)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:individual,company'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'segment_id' => ['nullable', Rule::exists(Segment::class, 'id')],
            'zone_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'payment_term_id' => ['nullable', 'integer'],
            'payment_method' => ['nullable', Rule::in(array_keys(Client::PAYMENT_METHODS))],
            'salesrep_id' => ['nullable', 'integer'],
            'credit_limit' => ['required', 'numeric', 'min:0'],
            'discount_rate' => ['required', 'numeric', 'between:0,100'],
            'price_tier' => ['required', Rule::in(array_keys(Client::PRICE_TIERS))],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
            'rccm' => ['nullable', 'string', 'max:100', Rule::unique(Client::class, 'rccm')->ignore($clientId)->whereNull('deleted_at')],
            'niu' => ['nullable', 'string', 'max:100', Rule::unique(Client::class, 'niu')->ignore($clientId)->whereNull('deleted_at')],
            'bp' => ['nullable', 'string', 'max:100'],
        ];

        if ($type === 'company') {
            $rules['rccm'][] = 'required';
            $rules['niu'][] = 'required';
            $rules['bp'] = ['required', 'string', 'max:100'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'code.required' => 'Le code client est obligatoire.',
            'code.unique' => 'Ce code client est déjà utilisé.',
            'name.required' => 'Le nom / la raison sociale est obligatoire.',
            'niu.unique' => 'Ce NIU est déjà attribué à un autre client.',
            'rccm.unique' => 'Ce RCCM est déjà attribué à un autre client.',
            'rccm.required' => 'Le RCCM est obligatoire pour une entreprise.',
            'niu.required' => 'Le NIU est obligatoire pour une entreprise.',
            'bp.required' => 'La BP est obligatoire pour une entreprise.',
            'email.email' => 'L\'adresse email n\'est pas valide.',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>  Règles par ligne de contact
     */
    public static function contactRules(): array
    {
        return [
            'contacts.*.first_name' => ['required', 'string', 'max:255'],
            'contacts.*.last_name' => ['nullable', 'string', 'max:255'],
            'contacts.*.role' => ['required', 'in:principal,buyer,accountant,director,technician,other'],
            'contacts.*.position' => ['nullable', 'string', 'max:255'],
            'contacts.*.email' => ['nullable', 'email:rfc', 'max:255'],
            'contacts.*.phone' => ['nullable', 'string', 'max:50'],
            'contacts.*.mobile' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>  Règles par ligne d'adresse
     */
    public static function addressRules(): array
    {
        return [
            'addresses.*.type' => ['required', 'in:billing,shipping,both'],
            'addresses.*.street' => ['nullable', 'string', 'max:255'],
            'addresses.*.city' => ['nullable', 'string', 'max:120'],
            'addresses.*.state' => ['nullable', 'string', 'max:120'],
            'addresses.*.postal_code' => ['nullable', 'string', 'max:40'],
            'addresses.*.country' => ['nullable', 'string', 'max:2'],
        ];
    }
}
