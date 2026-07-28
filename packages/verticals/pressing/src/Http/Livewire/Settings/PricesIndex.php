<?php

namespace Pressing\Http\Livewire\Settings;

use Livewire\Component;
use Pressing\Concerns\AuthorizesPressingActions;
use Pressing\Models\Agence;
use Pressing\Models\ArticlePrice;
use Pressing\Models\ArticleType;
use Pressing\Support\PressingBilling;
use Pressing\Support\PressingSettings;

class PricesIndex extends Component
{
    use AuthorizesPressingActions;

    public ?int $agenceFilter = null;

    public array $prices = [];

    public string $billingDefaultMode = PressingBilling::MODE_FIXED;

    public string $globalWeightPrice = '0';

    public string $typeFilter = 'all';

    public string $bulkFixedPrice = '';

    public string $bulkPerKgPrice = '';

    public function mount(): void
    {
        $this->authorizePressingAction('pressing_settings.view');
        $this->billingDefaultMode = PressingSettings::billingDefaultMode();
        $this->globalWeightPrice = (string) PressingSettings::globalWeightPrice();
        $this->loadPrices();
    }

    public function updatedAgenceFilter(): void
    {
        $this->loadPrices();
        $this->globalWeightPrice = (string) PressingSettings::globalWeightPrice($this->agenceFilter ?: null);
    }

    public function setDefaultMode(string $mode): void
    {
        if (! $this->can('pressing_settings.manage')) {
            return;
        }

        if (array_key_exists($mode, PressingBilling::modes())) {
            $this->billingDefaultMode = $mode;
        }
    }

    public function applyBulkFixed(): void
    {
        if (! $this->can('pressing_settings.manage')) {
            return;
        }

        $this->validate([
            'bulkFixedPrice' => ['required', 'numeric', 'min:0'],
        ]);

        foreach ($this->prices as $i => $row) {
            $this->prices[$i]['amount'] = (string) $this->bulkFixedPrice;
        }

        session()->flash('success', 'Prix fixe appliqué à tous les types (pensez à Enregistrer).');
    }

    public function applyBulkPerKg(): void
    {
        if (! $this->can('pressing_settings.manage')) {
            return;
        }

        $this->validate([
            'bulkPerKgPrice' => ['required', 'numeric', 'min:0'],
        ]);

        foreach ($this->prices as $i => $row) {
            $this->prices[$i]['price_per_kg'] = (string) $this->bulkPerKgPrice;
        }

        session()->flash('success', 'Prix/kg appliqué à tous les types (pensez à Enregistrer).');
    }

    public function loadPrices(): void
    {
        $types = ArticleType::orderBy('sort_order')->orderBy('name')->get();
        $this->prices = [];

        foreach ($types as $type) {
            $row = $type->priceRowForAgence($this->agenceFilter ?: null);

            $this->prices[] = [
                'article_type_id' => $type->id,
                'name' => $type->name,
                'code' => $type->code,
                'is_active' => $type->is_active,
                'amount' => (string) ($row?->amount ?? 0),
                'price_per_kg' => (string) ($row?->price_per_kg ?? 0),
            ];
        }
    }

    public function getCountsProperty(): array
    {
        $fixed = 0;
        $perKg = 0;
        $both = 0;

        foreach ($this->prices as $row) {
            $hasFixed = (float) ($row['amount'] ?? 0) > 0;
            $hasKg = (float) ($row['price_per_kg'] ?? 0) > 0;
            if ($hasFixed) {
                $fixed++;
            }
            if ($hasKg) {
                $perKg++;
            }
            if ($hasFixed && $hasKg) {
                $both++;
            }
        }

        return [
            'all' => count($this->prices),
            'fixed' => $fixed,
            'per_kg' => $perKg,
            'both' => $both,
        ];
    }

    public function save(): void
    {
        $this->authorizePressingAction('pressing_settings.manage');

        $this->validate([
            'billingDefaultMode' => ['required', 'in:' . implode(',', array_keys(PressingBilling::modes()))],
            'globalWeightPrice' => ['required', 'numeric', 'min:0'],
            'prices' => ['required', 'array'],
            'prices.*.article_type_id' => ['required', 'integer'],
            'prices.*.amount' => ['required', 'numeric', 'min:0'],
            'prices.*.price_per_kg' => ['required', 'numeric', 'min:0'],
        ]);

        foreach ($this->prices as $index => $row) {
            if ((float) $row['amount'] <= 0 && (float) ($row['price_per_kg'] ?? 0) <= 0) {
                $this->addError(
                    'prices.'.$index.'.amount',
                    '« '.$row['name'].' » : indiquez au moins un prix fixe ou un prix/kg.'
                );

                return;
            }
        }

        if ($this->billingDefaultMode === PressingBilling::MODE_WEIGHT_GLOBAL && (float) $this->globalWeightPrice <= 0) {
            $this->addError('globalWeightPrice', 'Indiquez le prix fixe au kilo (tout cou).');

            return;
        }

        PressingSettings::set(PressingSettings::KEY_BILLING_DEFAULT_MODE, $this->billingDefaultMode);

        if ($this->agenceFilter) {
            PressingSettings::set(
                PressingSettings::KEY_WEIGHT_PRICE_GLOBAL.'.agence.'.$this->agenceFilter,
                (float) $this->globalWeightPrice
            );
        } else {
            PressingSettings::set(PressingSettings::KEY_WEIGHT_PRICE_GLOBAL, (float) $this->globalWeightPrice);
        }

        foreach ($this->prices as $row) {
            $amount = (float) $row['amount'];
            $perKg = (float) ($row['price_per_kg'] ?? 0);
            // Prefer fixed as default article mode when both exist (for legacy reports)
            $mode = $amount > 0 ? PressingBilling::ARTICLE_FIXED : PressingBilling::ARTICLE_PER_KG;

            ArticleType::where('id', $row['article_type_id'])->update([
                'pricing_mode' => $mode,
            ]);

            ArticlePrice::updateOrCreate(
                [
                    'article_type_id' => (int) $row['article_type_id'],
                    'agence_id' => $this->agenceFilter ?: null,
                ],
                [
                    'amount' => $amount,
                    'price_per_kg' => $perKg > 0 ? $perKg : null,
                    'pricing_mode' => $mode,
                    'currency' => 'XAF',
                    'is_active' => true,
                ]
            );
        }

        session()->flash('success', 'Tarifs enregistrés : prix fixe, prix/kg par type et prix tout-cou.');
        $this->loadPrices();
    }

    public function render()
    {
        $filtered = collect($this->prices)->filter(function ($row) {
            $hasFixed = (float) ($row['amount'] ?? 0) > 0;
            $hasKg = (float) ($row['price_per_kg'] ?? 0) > 0;

            return match ($this->typeFilter) {
                'fixed' => $hasFixed,
                'per_kg' => $hasKg,
                'both' => $hasFixed && $hasKg,
                'missing' => ! $hasFixed || ! $hasKg,
                default => true,
            };
        });

        return view('pressing::livewire.settings.prices', [
            'agences' => Agence::where('is_active', true)->orderBy('name')->get(),
            'canManage' => $this->can('pressing_settings.manage'),
            'billingModes' => PressingBilling::modes(),
            'filteredPrices' => $filtered,
            'counts' => $this->counts,
        ])->layout('layouts.app', [
            'title' => 'Tarifs & facturation',
            'subtitle' => 'Prix fixe · Au kilo par type · Prix fixe au kilo (tout cou)',
        ]);
    }
}
