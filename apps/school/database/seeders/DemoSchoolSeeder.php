<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InovCom\Users\Models\Role;
use InovCom\Users\Models\User;
use School\Models\AcademicYear;
use School\Models\SchoolClass;
use School\Models\SchoolEnrollment;
use School\Models\SchoolFeeStructure;
use School\Models\SchoolPayment;
use School\Models\SchoolStudent;
use School\Models\SchoolSubject;
use School\Models\SchoolTeacher;
use School\Models\StudentIdCard;
use School\Support\SchoolNotificationSettings;
use School\Support\SchoolOptionCatalog;
use School\Support\SchoolRoleCatalog;
use School\Support\StudentLedgerService;

/**
 * Demo dataset for a typical secondary school tenant.
 * Run: php artisan tenant:seed-school-demo {tenantCode}
 */
class DemoSchoolSeeder extends Seeder
{
    public function run(): void
    {
        SchoolOptionCatalog::seedDefaults();
        SchoolNotificationSettings::seedDefaults();
        SchoolRoleCatalog::sync();

        $year = AcademicYear::query()->updateOrCreate(
            ['code' => '2025-2026'],
            [
                'name' => 'Année académique 2025-2026',
                'start_date' => '2025-09-01',
                'end_date' => '2026-06-30',
                'is_active' => true,
            ]
        );
        AcademicYear::query()->where('id', '!=', $year->id)->update(['is_active' => false]);

        $classes = collect([
            ['name' => '6ème A', 'section' => 'A'],
            ['name' => '5ème A', 'section' => 'A'],
            ['name' => '3ème Exp.', 'section' => 'B'],
        ])->map(function (array $row) {
            return SchoolClass::query()->updateOrCreate(
                ['name' => $row['name']],
                [
                    'section' => $row['section'],
                    'is_active' => true,
                ]
            );
        });

        foreach ([['MATH', 'Mathématiques'], ['FR', 'Français'], ['ANG', 'Anglais'], ['SVT', 'SVT']] as [$code, $name]) {
            SchoolSubject::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'is_active' => true]
            );
        }

        SchoolTeacher::query()->updateOrCreate(
            ['email' => 'enseignant.demo@school.test'],
            [
                'teacher_code' => 'ENS-2025-0001',
                'first_name' => 'Marie',
                'last_name' => 'Nguema',
                'full_name' => 'Marie Nguema',
                'gender' => 'F',
                'phone' => '670000001',
                'address' => 'Yaoundé',
                'education_level' => 'licence',
                'diploma_kind' => 'licence',
                'teaching_section' => 'lycee',
                'schedule_note' => 'Temps plein',
                'profile_status' => 'draft',
                'is_active' => true,
            ]
        );

        foreach ([
            [$classes[0]->id, 75000],
            [$classes[1]->id, 80000],
            [$classes[2]->id, 90000],
        ] as [$classId, $amount]) {
            SchoolFeeStructure::query()->updateOrCreate(
                [
                    'name' => 'Scolarité',
                    'academic_year_id' => $year->id,
                    'class_id' => $classId,
                ],
                [
                    'amount' => $amount,
                    'currency_code' => 'XOF',
                    'is_active' => true,
                    'description' => 'Frais de scolarité annuels',
                ]
            );
        }

        $studentsSpec = [
            ['SCH-2025-0001', 'Amina', 'Diallo', 'F', 'Parent Diallo', '670111111', 'parent.diallo@demo.test', $classes[0], 'A'],
            ['SCH-2025-0002', 'Jean', 'Mbarga', 'M', 'Parent Mbarga', '670222222', 'parent.mbarga@demo.test', $classes[0], 'A'],
            ['SCH-2025-0003', 'Fatou', 'Sow', 'F', 'Parent Sow', '670333333', null, $classes[1], 'A'],
            ['SCH-2025-0004', 'Paul', 'Okeke', 'M', 'Parent Okeke', '670444444', 'parent.okeke@demo.test', $classes[1], 'A'],
            ['SCH-2025-0005', 'Grace', 'Tchoumi', 'F', 'Parent Tchoumi', '670555555', null, $classes[2], 'B'],
            ['SCH-2025-0006', 'Ibrahim', 'Hassan', 'M', 'Parent Hassan', '670666666', 'parent.hassan@demo.test', $classes[2], 'B'],
        ];

        $ledger = app(StudentLedgerService::class);
        $students = [];

        foreach ($studentsSpec as $spec) {
            [$code, $first, $last, $gender, $parent, $phone, $email, $class, $section] = $spec;
            $student = SchoolStudent::query()->updateOrCreate(
                ['student_code' => $code],
                [
                    'first_name' => $first,
                    'last_name' => $last,
                    'gender' => $gender,
                    'birth_date' => now()->subYears(rand(11, 16))->format('Y-m-d'),
                    'parent_full_name' => $parent,
                    'parent_phone' => $phone,
                    'parent_email' => $email,
                    'is_active' => true,
                ]
            );
            $students[] = $student;

            SchoolEnrollment::query()->updateOrCreate(
                ['student_id' => $student->id, 'academic_year_id' => $year->id],
                ['class_id' => $class->id, 'section' => $section, 'status' => 'enrolled']
            );

            $ledger->chargeFeesForEnrollment((int) $student->id, (int) $year->id, (int) $class->id);

            StudentIdCard::query()->updateOrCreate(
                ['student_id' => $student->id, 'academic_year_id' => $year->id],
                [
                    'batch_code' => 'DEMO-2025',
                    'qr_token' => (string) Str::uuid(),
                    'barcode_data' => $student->student_code.'-DEMO-2025',
                    'generated_at' => now(),
                ]
            );
        }

        // One onsite verified payment for first student
        $payment = SchoolPayment::query()->updateOrCreate(
            [
                'student_id' => $students[0]->id,
                'academic_year_id' => $year->id,
                'reference' => 'DEMO-PAY-001',
            ],
            [
                'payment_type' => 'onsite',
                'currency_code' => 'XOF',
                'amount' => 25000,
                'status' => 'verified',
                'paid_at' => now()->subDays(2),
                'verified_at' => now()->subDays(2),
                'verified_by_name' => 'Caissier Demo',
                'payer_name' => $students[0]->parent_full_name,
            ]
        );
        $ledger->recordCreditFromPayment($payment);

        $this->seedUsers();
    }

    protected function seedUsers(): void
    {
        $defs = [
            ['directeur.demo@school.test', 'Directeur Demo', 'directeur', 'Directeur#2025'],
            ['enseignant.demo@school.test', 'Enseignant Demo', 'enseignant', 'Enseignant#2025'],
            ['caissier.demo@school.test', 'Caissier Demo', 'caissier', 'Caissier#2025'],
        ];

        foreach ($defs as [$email, $name, $roleName, $password]) {
            $user = User::on('tenant')->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make($password),
                    'is_active' => true,
                ]
            );

            $role = Role::on('tenant')->where('name', $roleName)->first();
            if ($role) {
                $user->roles()->syncWithoutDetaching([$role->id]);
            }
        }
    }
}
