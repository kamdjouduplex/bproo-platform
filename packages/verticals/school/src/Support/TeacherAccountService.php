<?php

namespace School\Support;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InovCom\Users\Models\Role;
use InovCom\Users\Models\User;
use School\Models\SchoolTeacher;

class TeacherAccountService
{
    public static function ensureUser(SchoolTeacher $teacher, string $plainPassword, bool $isActive): User
    {
        $email = filled($teacher->email)
            ? strtolower(trim((string) $teacher->email))
            : self::syntheticEmail($teacher);

        $taken = User::on('tenant')->where('email', $email)
            ->when($teacher->user_id, fn ($q) => $q->where('id', '!=', $teacher->user_id))
            ->exists();
        if ($taken) {
            throw new \RuntimeException('Cet email est déjà utilisé par un autre compte.');
        }

        $user = $teacher->user_id
            ? User::on('tenant')->find($teacher->user_id)
            : null;

        if (! $user) {
            $user = User::on('tenant')->make();
            $user->remember_token = Str::random(10);
        }

        $user->name = $teacher->full_name;
        $user->email = $email;
        $user->is_active = $isActive;
        if (Schema::connection('tenant')->hasColumn('users', 'phone')) {
            $digits = preg_replace('/\D+/', '', (string) $teacher->phone) ?? '';
            $user->phone = $digits !== '' ? $digits : null;
        }
        if ($plainPassword !== '') {
            $user->password = Hash::make($plainPassword);
        }
        $user->save();

        $role = Role::on('tenant')->where('name', 'enseignant')->first();
        if ($role && ! $user->roles->contains('id', $role->id)) {
            $user->roles()->syncWithoutDetaching([$role->id]);
        }

        if ((int) $teacher->user_id !== (int) $user->id) {
            $teacher->user_id = $user->id;
            $teacher->save();
        }

        return $user;
    }

    public static function syncUser(SchoolTeacher $teacher): void
    {
        if (! $teacher->user_id) {
            return;
        }

        $user = User::on('tenant')->find($teacher->user_id);
        if (! $user) {
            return;
        }

        $user->name = $teacher->full_name;
        if (filled($teacher->email) && ! str_ends_with((string) $user->email, '@compte.local')) {
            $user->email = strtolower(trim((string) $teacher->email));
        } elseif (filled($teacher->email)) {
            $user->email = strtolower(trim((string) $teacher->email));
        }
        if (Schema::connection('tenant')->hasColumn('users', 'phone')) {
            $digits = preg_replace('/\D+/', '', (string) $teacher->phone) ?? '';
            $user->phone = $digits !== '' ? $digits : null;
        }
        $user->is_active = (bool) $teacher->is_active;
        $user->save();
    }

    public static function setActive(SchoolTeacher $teacher, bool $active): void
    {
        $teacher->update(['is_active' => $active]);
        self::syncUser($teacher->fresh());
    }

    public static function generatePassword(): string
    {
        return Str::upper(Str::random(4)).Str::lower(Str::random(4)).random_int(10, 99);
    }

    public static function syntheticEmail(SchoolTeacher $teacher): string
    {
        $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '.', (string) $teacher->teacher_code));
        $slug = trim($slug, '.') ?: ('id'.$teacher->id);

        return 'ens.'.$slug.'@compte.local';
    }
}
