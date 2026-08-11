<?php

namespace School\Http\Livewire;

use Illuminate\Support\Facades\Route;
use Livewire\Component;

class SchoolHub extends Component
{
    public function render()
    {
        $links = array_values(array_filter([
            Route::has('tenant.school.years.index') ? [
                'label' => 'Années académiques',
                'route' => 'tenant.school.years.index',
                'hint' => 'Créer / gérer les années',
            ] : null,
            Route::has('tenant.school.students.index') ? [
                'label' => 'Étudiants',
                'route' => 'tenant.school.students.index',
                'hint' => 'Profils + historique',
            ] : null,
            Route::has('tenant.school.enrollments.index') ? [
                'label' => 'Inscriptions',
                'route' => 'tenant.school.enrollments.index',
                'hint' => 'Enrollement par année',
            ] : null,
            Route::has('tenant.school.payments.index') ? [
                'label' => 'Paiements',
                'route' => 'tenant.school.payments.index',
                'hint' => 'Banque & paiements à l’école',
            ] : null,
        ]));

        return view('school::livewire.hub', ['links' => $links])
            ->layout('layouts.app', [
                'title' => 'École',
                'subtitle' => 'Bproo School — hub métier',
            ]);
    }
}

