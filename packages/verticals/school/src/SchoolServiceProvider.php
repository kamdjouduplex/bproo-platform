<?php

namespace School;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Kernel\Traits\LazyModuleBoot;
use Livewire\Livewire;
use School\Http\Controllers\SchoolIdCardPrintController;
use School\Http\Controllers\SchoolPaymentProofController;
use School\Http\Controllers\SchoolReceiptPrintController;
use School\Http\Controllers\SchoolReportCardPrintController;
use School\Http\Controllers\SchoolStudentPhotoController;
use School\Http\Livewire\SchoolAuditIndex;
use School\Http\Livewire\SchoolClassesDetail;
use School\Http\Livewire\SchoolClassesIndex;
use School\Http\Livewire\SchoolEnrollmentsDetail;
use School\Http\Livewire\SchoolEnrollmentsIndex;
use School\Http\Livewire\SchoolExamsDetail;
use School\Http\Livewire\SchoolExamsIndex;
use School\Http\Livewire\SchoolCoefficientsDetail;
use School\Http\Livewire\SchoolCoefficientsIndex;
use School\Http\Livewire\SchoolFeesDetail;
use School\Http\Livewire\SchoolFeesIndex;
use School\Http\Livewire\SchoolGlobalSearch;
use School\Http\Livewire\SchoolGradingRulesDetail;
use School\Http\Livewire\SchoolGradingRulesIndex;
use School\Http\Livewire\SchoolGradingSystemsDetail;
use School\Http\Livewire\SchoolGradingSystemsIndex;
use School\Http\Livewire\SchoolHub;
use School\Http\Livewire\SchoolIdCardsDetail;
use School\Http\Livewire\SchoolIdCardsIndex;
use School\Http\Livewire\SchoolLanguagesIndex;
use School\Http\Livewire\SchoolNotificationsIndex;
use School\Http\Livewire\SchoolOptionsDetail;
use School\Http\Livewire\SchoolOptionsIndex;
use School\Http\Livewire\SchoolPaymentsDetail;
use School\Http\Livewire\SchoolPaymentsIndex;
use School\Http\Livewire\SchoolPublicationRulesDetail;
use School\Http\Livewire\SchoolPublicationRulesIndex;
use School\Http\Livewire\SchoolPublicationsDetail;
use School\Http\Livewire\SchoolPublicationsIndex;
use School\Http\Livewire\SchoolReportCardsIndex;
use School\Http\Livewire\SchoolStudentsDetail;
use School\Http\Livewire\SchoolStudentsIndex;
use School\Http\Livewire\SchoolSubjectsDetail;
use School\Http\Livewire\SchoolSubjectsIndex;
use School\Http\Livewire\SchoolTeachersDetail;
use School\Http\Livewire\SchoolTeachersIndex;
use School\Http\Livewire\SchoolTuitionIndex;
use School\Http\Livewire\SchoolYearsDetail;
use School\Http\Livewire\SchoolYearsIndex;

class SchoolServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    /** Foundation key — provider also boots when any school feature module is enabled. */
    protected string $moduleKey = 'school_years';

    protected array $alsoBootWhenModules = [
        'school',
        'school_classes',
        'school_subjects',
        'school_teachers',
        'school_students',
        'school_enrollments',
        'school_payments',
        'school_id_cards',
        'school_exams',
        'school_grading',
        'school_publications',
        'school_report_cards',
        'school_fees',
        'school_notifications',
        'school_settings',
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/school.php', 'school');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant_modules'),
        ], 'bproo-school-migrations');

        $this->publishes([
            __DIR__.'/../config/school.php' => config_path('school.php'),
        ], 'bproo-school-config');
    }

    public function boot(): void
    {
        if (! $this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'school');

        Livewire::component('school.hub', SchoolHub::class);
        Livewire::component('school.global-search', SchoolGlobalSearch::class);
        Livewire::component('school.years-index', SchoolYearsIndex::class);
        Livewire::component('school.years-detail', SchoolYearsDetail::class);
        Livewire::component('school.classes-index', SchoolClassesIndex::class);
        Livewire::component('school.classes-detail', SchoolClassesDetail::class);
        Livewire::component('school.subjects-index', SchoolSubjectsIndex::class);
        Livewire::component('school.subjects-detail', SchoolSubjectsDetail::class);
        Livewire::component('school.teachers-index', SchoolTeachersIndex::class);
        Livewire::component('school.teachers-detail', SchoolTeachersDetail::class);
        Livewire::component('school.students-index', SchoolStudentsIndex::class);
        Livewire::component('school.students-detail', SchoolStudentsDetail::class);
        Livewire::component('school.enrollments-index', SchoolEnrollmentsIndex::class);
        Livewire::component('school.enrollments-detail', SchoolEnrollmentsDetail::class);
        Livewire::component('school.exams-index', SchoolExamsIndex::class);
        Livewire::component('school.exams-detail', SchoolExamsDetail::class);
        Livewire::component('school.grading-systems-index', SchoolGradingSystemsIndex::class);
        Livewire::component('school.grading-systems-detail', SchoolGradingSystemsDetail::class);
        Livewire::component('school.coefficients-index', SchoolCoefficientsIndex::class);
        Livewire::component('school.coefficients-detail', SchoolCoefficientsDetail::class);
        Livewire::component('school.grading-rules-index', SchoolGradingRulesIndex::class);
        Livewire::component('school.grading-rules-detail', SchoolGradingRulesDetail::class);
        Livewire::component('school.publications-index', SchoolPublicationsIndex::class);
        Livewire::component('school.publications-detail', SchoolPublicationsDetail::class);
        Livewire::component('school.publication-rules-index', SchoolPublicationRulesIndex::class);
        Livewire::component('school.publication-rules-detail', SchoolPublicationRulesDetail::class);
        Livewire::component('school.report-cards-index', SchoolReportCardsIndex::class);
        Livewire::component('school.fees-index', SchoolFeesIndex::class);
        Livewire::component('school.fees-detail', SchoolFeesDetail::class);
        Livewire::component('school.notifications-index', SchoolNotificationsIndex::class);
        Livewire::component('school.payments-index', SchoolPaymentsIndex::class);
        Livewire::component('school.payments-detail', SchoolPaymentsDetail::class);
        Livewire::component('school.tuition-index', SchoolTuitionIndex::class);
        Livewire::component('school.id-cards-index', SchoolIdCardsIndex::class);
        Livewire::component('school.id-cards-detail', SchoolIdCardsDetail::class);
        Livewire::component('school.options-index', SchoolOptionsIndex::class);
        Livewire::component('school.options-detail', SchoolOptionsDetail::class);
        Livewire::component('school.languages-index', SchoolLanguagesIndex::class);
        Livewire::component('school.audit-index', SchoolAuditIndex::class);

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/school', function () {
                    return redirect()->route('tenant.dashboard', request()->query());
                })
                    ->middleware(['module:school'])
                    ->name('tenant.school.hub');

                Route::middleware(['module:school_years'])->group(function () {
                    Route::get('/school/years', SchoolYearsIndex::class)->name('tenant.school.years.index');
                    Route::get('/school/years/{id}', SchoolYearsDetail::class)->name('tenant.school.years.show');
                    Route::get('/school/years/{id}/manage', SchoolYearsDetail::class)->name('tenant.school.years.manage');
                });

                Route::middleware(['module:school_classes'])->group(function () {
                    Route::get('/school/classes', SchoolClassesIndex::class)->name('tenant.school.classes.index');
                    Route::get('/school/classes/{id}', SchoolClassesDetail::class)->name('tenant.school.classes.show');
                    Route::get('/school/classes/{id}/manage', SchoolClassesDetail::class)->name('tenant.school.classes.manage');
                });

                Route::middleware(['module:school_subjects'])->group(function () {
                    Route::get('/school/subjects', SchoolSubjectsIndex::class)->name('tenant.school.subjects.index');
                    Route::get('/school/subjects/{id}', SchoolSubjectsDetail::class)->name('tenant.school.subjects.show');
                    Route::get('/school/subjects/{id}/manage', SchoolSubjectsDetail::class)->name('tenant.school.subjects.manage');
                });

                Route::middleware(['module:school_teachers'])->group(function () {
                    Route::get('/school/teachers', SchoolTeachersIndex::class)->name('tenant.school.teachers.index');
                    Route::get('/school/teachers/{id}', SchoolTeachersDetail::class)->name('tenant.school.teachers.show');
                    Route::get('/school/teachers/{id}/manage', SchoolTeachersDetail::class)->name('tenant.school.teachers.manage');
                });

                Route::middleware(['module:school_students'])->group(function () {
                    Route::get('/school/students', SchoolStudentsIndex::class)->name('tenant.school.students.index');
                    Route::get('/school/students/{id}', SchoolStudentsDetail::class)->name('tenant.school.students.show');
                    Route::get('/school/students/{id}/manage', SchoolStudentsDetail::class)->name('tenant.school.students.manage');
                    Route::get('/school/students/{student}/photo', SchoolStudentPhotoController::class)->name('tenant.school.students.photo')->whereNumber('student');
                });

                Route::middleware(['module:school_enrollments'])->group(function () {
                    Route::get('/school/enrollments', SchoolEnrollmentsIndex::class)->name('tenant.school.enrollments.index');
                    Route::get('/school/enrollments/{id}', SchoolEnrollmentsDetail::class)->name('tenant.school.enrollments.show');
                    Route::get('/school/enrollments/{id}/manage', SchoolEnrollmentsDetail::class)->name('tenant.school.enrollments.manage');
                });

                Route::middleware(['module:school_exams'])->group(function () {
                    Route::get('/school/exams', SchoolExamsIndex::class)->name('tenant.school.exams.index');
                    Route::get('/school/exams/{id}', SchoolExamsDetail::class)->name('tenant.school.exams.show');
                    Route::get('/school/exams/{id}/manage', SchoolExamsDetail::class)->name('tenant.school.exams.manage');
                });

                Route::middleware(['module:school_grading'])->group(function () {
                    Route::get('/school/grading', SchoolGradingSystemsIndex::class)->name('tenant.school.grading.systems.index');
                    Route::get('/school/grading/systems/{id}', SchoolGradingSystemsDetail::class)->name('tenant.school.grading.systems.show');
                    Route::get('/school/grading/systems/{id}/manage', SchoolGradingSystemsDetail::class)->name('tenant.school.grading.systems.manage');

                    Route::get('/school/grading/coefficients', SchoolCoefficientsIndex::class)->name('tenant.school.grading.coefficients.index');
                    Route::get('/school/grading/coefficients/{id}', SchoolCoefficientsDetail::class)->name('tenant.school.grading.coefficients.show');
                    Route::get('/school/grading/coefficients/{id}/manage', SchoolCoefficientsDetail::class)->name('tenant.school.grading.coefficients.manage');

                    Route::get('/school/grading/rules', SchoolGradingRulesIndex::class)->name('tenant.school.grading.rules.index');
                    Route::get('/school/grading/rules/{id}', SchoolGradingRulesDetail::class)->name('tenant.school.grading.rules.show');
                    Route::get('/school/grading/rules/{id}/manage', SchoolGradingRulesDetail::class)->name('tenant.school.grading.rules.manage');
                });

                Route::middleware(['module:school_publications'])->group(function () {
                    Route::get('/school/publications', SchoolPublicationsIndex::class)->name('tenant.school.publications.index');
                    Route::get('/school/publications/rules', SchoolPublicationRulesIndex::class)->name('tenant.school.publications.rules.index');
                    Route::get('/school/publications/rules/{id}', SchoolPublicationRulesDetail::class)->name('tenant.school.publications.rules.show');
                    Route::get('/school/publications/rules/{id}/manage', SchoolPublicationRulesDetail::class)->name('tenant.school.publications.rules.manage');
                    Route::get('/school/publications/{id}', SchoolPublicationsDetail::class)->name('tenant.school.publications.show')->whereNumber('id');
                    Route::get('/school/publications/{id}/manage', SchoolPublicationsDetail::class)->name('tenant.school.publications.manage')->whereNumber('id');
                });

                Route::middleware(['module:school_report_cards'])->group(function () {
                    Route::get('/school/report-cards', SchoolReportCardsIndex::class)->name('tenant.school.report_cards.index');
                    Route::get('/school/report-cards/print', SchoolReportCardPrintController::class)->name('tenant.school.report_cards.print');
                });

                Route::middleware(['module:school_fees'])->group(function () {
                    Route::get('/school/fees', SchoolFeesIndex::class)->name('tenant.school.fees.index');
                    Route::get('/school/fees/{id}', SchoolFeesDetail::class)->name('tenant.school.fees.show')->whereNumber('id');
                    Route::get('/school/fees/{id}/manage', SchoolFeesDetail::class)->name('tenant.school.fees.manage')->whereNumber('id');
                });

                Route::middleware(['module:school_notifications'])->group(function () {
                    Route::get('/school/notifications', SchoolNotificationsIndex::class)->name('tenant.school.notifications.index');
                });

                Route::middleware(['module:school_payments'])->group(function () {
                    Route::get('/school/payments', SchoolPaymentsIndex::class)->name('tenant.school.payments.index');
                    Route::get('/school/tuition', SchoolTuitionIndex::class)->name('tenant.school.tuition.index');
                    Route::get('/school/payments/{id}', SchoolPaymentsDetail::class)->name('tenant.school.payments.show')->whereNumber('id');
                    Route::get('/school/payments/{id}/manage', SchoolPaymentsDetail::class)->name('tenant.school.payments.manage')->whereNumber('id');
                    Route::get('/school/payments/{payment}/receipt', SchoolReceiptPrintController::class)->name('tenant.school.receipts.print');
                    Route::get('/school/payments/{payment}/proof', SchoolPaymentProofController::class)->name('tenant.school.payments.proof')->whereNumber('payment');
                });

                Route::middleware(['module:school_id_cards'])->group(function () {
                    Route::get('/school/id-cards', SchoolIdCardsIndex::class)->name('tenant.school.id_cards.index');
                    Route::get('/school/id-cards/print', SchoolIdCardPrintController::class)->name('tenant.school.id_cards.print');
                    Route::get('/school/id-cards/{id}', SchoolIdCardsDetail::class)->name('tenant.school.id_cards.show')->whereNumber('id');
                    Route::get('/school/id-cards/{id}/manage', SchoolIdCardsDetail::class)->name('tenant.school.id_cards.manage')->whereNumber('id');
                });

                Route::middleware(['module:school_settings'])->group(function () {
                    Route::get('/school/options', SchoolOptionsIndex::class)->name('tenant.school.options.index');
                    Route::get('/school/options/{id}', SchoolOptionsDetail::class)->name('tenant.school.options.show');
                    Route::get('/school/options/{id}/manage', SchoolOptionsDetail::class)->name('tenant.school.options.manage');
                    Route::get('/school/languages', SchoolLanguagesIndex::class)->name('tenant.school.languages.index');
                    Route::get('/school/audit', SchoolAuditIndex::class)->name('tenant.school.audit.index');
                });
            });
    }
}
