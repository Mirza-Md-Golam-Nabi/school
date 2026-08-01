<?php

namespace Database\Seeders;

use App\Enums\AddressType;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\Religion;
use App\Enums\UserType;
use App\Models\User;
use Database\Seeders\Helpers\LocationData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TeacherSeeder extends Seeder
{
    private string $hashedPassword;

    /** @var array<int, string> */
    private array $maleNames = [
        'Md. Aminul Haque', 'Md. Kamruzzaman', 'Md. Shahidul Islam', 'Md. Nurul Amin',
        'Md. Mostafizur Rahman',
    ];

    /** @var array<int, string> */
    private array $femaleNames = [
        'Nasima Begum', 'Ferdousi Akter', 'Shahnaz Parvin', 'Hosne Ara Begum',
        'Selina Akhter',
    ];

    /** @var array<int, string> */
    private array $uniqueDesignations = [
        'Head Teacher', 'Assistant Head Teacher',
    ];

    /** @var array<int, string> */
    private array $designations = [
        'Senior Teacher', 'Assistant Teacher', 'Physical Education Teacher',
    ];

    /** @var array<int, string> */
    private array $qualifications = [
        'B.A. (Honours), M.A.', 'B.Sc., M.Sc.', 'B.Ed., M.Ed.',
        'B.A., B.Ed.', 'M.Sc. in Mathematics',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->hashedPassword = Hash::make('password');

        DB::transaction(function () {
            $email = UserType::Teacher->value.'@example.com';
            $user = User::where('email', $email)->first();
            $user->teacherProfile()->firstOrCreate([], [
                'gender' => Gender::Male,
            ]);

            $teachers = collect($this->maleNames)->map(fn (string $name) => ['name' => $name, 'is_male' => true])
                ->merge(collect($this->femaleNames)->map(fn (string $name) => ['name' => $name, 'is_male' => false]))
                ->shuffle()
                ->values();

            foreach ($teachers as $index => $teacher) {
                $designation = $this->uniqueDesignations[$index] ?? fake()->randomElement($this->designations);

                $this->createTeacher($teacher['name'], $teacher['is_male'], $designation);
            }
        });
    }

    private function createTeacher(string $name, bool $isMale, string $designation): void
    {
        $email = $this->emailFromName($name);

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'email_verified_at' => now(),
                'password' => $this->hashedPassword,
                'user_type' => UserType::Teacher,
                'is_active' => true,
            ]
        );

        if (! $user->hasRole('teacher')) {
            $user->assignRole('teacher');
        }

        if ($user->teacherProfile) {
            return;
        }

        $profile = $user->teacherProfile()->create([
            'gender' => $isMale ? Gender::Male : Gender::Female,
            'religion' => Religion::Muslim,
            'nationality' => 'Bangladeshi',
            'designation' => $designation,
            'qualification' => fake()->randomElement($this->qualifications),
            'status' => EmploymentStatus::Active,
        ]);

        $isSameAddress = fake()->boolean(70);

        $profile->addresses()->create([
            'type' => AddressType::Present,
            'address' => $this->randomAddress(),
            'is_same' => $isSameAddress,
        ]);

        if (! $isSameAddress) {
            $profile->addresses()->create([
                'type' => AddressType::Permanent,
                'address' => $this->randomAddress(),
                'is_same' => false,
            ]);
        }
    }

    private function emailFromName(string $name): string
    {
        $local = Str::of($name)
            ->replace('Md.', '')
            ->squish()
            ->lower()
            ->replace(' ', '.');

        return $local.'@example.com';
    }

    private function randomAddress(): string
    {
        return sprintf(
            'House #%d, Road #%d, %s, %s',
            fake()->numberBetween(1, 50),
            fake()->numberBetween(1, 20),
            fake()->randomElement(LocationData::areas()),
            fake()->randomElement(LocationData::districts())
        );
    }
}
