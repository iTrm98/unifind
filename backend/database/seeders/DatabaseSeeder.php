<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Creates the admin account, the student account and the category and
 * location lists the forms need. Nothing else.
 *
 * Safe to run again (it runs on every production container start): rows are
 * matched by email or name, so nothing is duplicated. The two accounts get
 * their name, role and password from ADMIN_PASSWORD / STUDENT_PASSWORD.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminPassword = (string) config('unifind.admin_password');
        $studentPassword = (string) config('unifind.student_password');

        if ($adminPassword === '' || $studentPassword === '') {
            throw new RuntimeException('Set ADMIN_PASSWORD and STUDENT_PASSWORD in backend/.env before seeding.');
        }

        $this->syncAccount('admin@uqu.edu.sa', 'Lost & Found Office', 'admin', $adminPassword);
        $this->syncAccount('student@uqu.edu.sa', 'Student', 'student', $studentPassword);

        foreach (['Electronics', 'Wallets & Cards', 'Keys', 'Books & Notes', 'Bags', 'Clothing', 'ID & Documents', 'Other'] as $name) {
            Category::firstOrCreate(['name' => $name]);
        }

        foreach (['Main Library', 'Engineering Building', 'Cafeteria', 'Computer Science Lab', 'Mosque', 'Parking Lot A', 'Student Affairs', 'Lecture Hall B2', 'Sports Hall'] as $name) {
            Location::firstOrCreate(['name' => $name]);
        }
    }

    private function syncAccount(string $email, string $name, string $role, string $password): void
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->fill(['name' => $name, 'role' => $role]);

        // Only re-hash when the password actually changed.
        if (! $user->exists || ! Hash::check($password, $user->password)) {
            $user->password = $password;
        }

        $user->save();
    }
}
