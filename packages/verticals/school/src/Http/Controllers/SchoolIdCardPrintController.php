<?php

namespace School\Http\Controllers;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use App\Support\PrintDocument;
use Illuminate\Http\Request;
use Illuminate\View\View;
use School\Http\Controllers\Concerns\AuthorizesSchoolHttp;
use School\Models\SchoolEnrollment;
use School\Models\StudentIdCard;
use School\Support\SchoolQrCode;

class SchoolIdCardPrintController
{
    use AuthorizesSchoolHttp;

    public function __invoke(Request $request): View
    {
        $this->authorizeSchoolPermission('school_id_cards.view');

        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);

        $ids = array_filter(array_map('intval', (array) $request->query('ids', [])));
        $batch = trim((string) $request->query('batch', ''));
        $yearId = $request->integer('year') ?: null;

        $query = StudentIdCard::query()->with(['student', 'academicYear']);

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        } elseif ($batch !== '') {
            $query->where('batch_code', $batch);
            if ($yearId) {
                $query->where('academic_year_id', $yearId);
            }
        } elseif ($request->filled('id')) {
            $query->where('id', (int) $request->query('id'));
        } else {
            abort(404, 'Aucune carte à imprimer.');
        }

        $cards = $query->orderBy('id')->get();
        if ($cards->isEmpty()) {
            abort(404, 'Aucune carte trouvée.');
        }

        // Prefetch enrollments in one query (class/section labels).
        $studentIds = $cards->pluck('student_id')->unique()->values()->all();
        $yearIds = $cards->pluck('academic_year_id')->unique()->values()->all();

        $enrollments = SchoolEnrollment::query()
            ->with('schoolClass')
            ->whereIn('student_id', $studentIds)
            ->whereIn('academic_year_id', $yearIds)
            ->get()
            ->keyBy(fn ($e) => $e->student_id.':'.$e->academic_year_id);

        $cards->each(function (StudentIdCard $card) use ($enrollments) {
            // Use cached SVG only; generate missing ones once (e.g. legacy cards).
            if (empty($card->qr_svg)) {
                SchoolQrCode::ensureCached($card);
            }

            $key = $card->student_id.':'.$card->academic_year_id;
            $enrollment = $enrollments->get($key);
            $card->class_label = $enrollment?->schoolClass?->name;
            $card->section_label = $enrollment?->section ?: $enrollment?->schoolClass?->section;
            $card->photo_src = $this->resolvePhotoSrc($card->student?->photo_path);
        });

        $label = $cards->count() === 1
            ? ($cards->first()->student?->student_code ?? 'carte')
            : 'lot-'.$cards->count();

        return view('school::print.id-cards', array_merge([
            'cards' => $cards,
            'settings' => $settings,
            'shopName' => $settings['shop_name'] ?? ($tenant?->name ?? 'Bproo School'),
            'logoSrc' => $settings['logo_embed_src'] ?? $settings['logo_url'] ?? null,
        ], PrintDocument::context(
            $request,
            'cartes-id',
            $label,
            'tenant.school.id_cards.index'
        )));
    }

    private function resolvePhotoSrc(?string $photoPath): ?string
    {
        return \School\Support\StudentPhotoStorage::dataUri($photoPath)
            ?? \School\Support\StudentPhotoStorage::url($photoPath);
    }
}
