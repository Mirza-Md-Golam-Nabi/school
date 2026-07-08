<?php

namespace Database\Seeders;

use App\Enums\AddressType;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\Classes;
use App\Models\User;
use Database\Seeders\Helpers\LocationData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StudentSeeder extends Seeder
{
    /** @var array<int, string> */
    private array $maleNames = [
        'Md. Rakibul Islam', 'Md. Shakil Ahmed', 'Md. Tanvir Hasan', 'Md. Imran Hossain',
        'Md. Arafat Rahman', 'Md. Sabbir Ahmed', 'Md. Nayeem Islam', 'Md. Fahim Faisal',
        'Md. Rezaul Karim', 'Md. Shariar Kabir', 'Md. Mahmudul Hasan', 'Md. Asraful Alam',
        'Md. Jahidul Islam', 'Md. Robiul Awal', 'Md. Kamrul Hasan', 'Md. Sohel Rana',
        'Md. Nazmul Huda', 'Md. Anisur Rahman', 'Md. Golam Mostafa', 'Md. Aminul Islam',
        'Md. Habibur Rahman', 'Md. Zahidul Islam', 'Md. Mizanur Rahman', 'Md. Firoz Kabir',
        'Md. Delwar Hossain', 'Md. Shafiul Alam', 'Md. Rasel Mia', 'Md. Ibrahim Khalil',
        'Md. Foysal Ahmed', 'Md. Emran Hossain',
    ];

    /** @var array<int, string> */
    private array $femaleNames = [
        'Fatema Akter', 'Nusrat Jahan', 'Sumaiya Islam', 'Tania Sultana',
        'Marium Khatun', 'Jannatul Ferdous', 'Rima Akter', 'Sabrina Yasmin',
        'Nasrin Sultana', 'Shirin Akter', 'Rehana Parvin', 'Salma Khatun',
        'Sultana Razia', 'Ayesha Siddika', 'Rupa Akter', 'Rina Khatun',
        'Farzana Yeasmin', 'Tahmina Akter', 'Kohinoor Khatun', 'Shahana Parvin',
        'Mahmuda Khatun', 'Shabnam Sultana', 'Taslima Khatun', 'Rowshan Ara',
        'Afsana Mimi', 'Sharmin Sultana', 'Lubna Yasmin', 'Nazma Khatun',
        'Ismat Ara', 'Tasnim Jahan',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sessionYear = (int) now()->format('Y');

        $classes = Classes::with(['sections', 'groups'])
            ->active()
            ->orderBy('order')
            ->get();

        foreach ($classes as $class) {
            $studentCount = fake()->numberBetween(5, 10);

            for ($rollNo = 1; $rollNo <= $studentCount; $rollNo++) {
                $this->createStudent($class, $rollNo, $sessionYear);
            }
        }
    }

    private function createStudent(Classes $class, int $rollNo, int $sessionYear): void
    {
        $isMale = fake()->boolean();
        $name = $isMale
            ? fake()->randomElement($this->maleNames)
            : fake()->randomElement($this->femaleNames);

        $email = sprintf('class_%02d_%02d@example.com', $class->order, $rollNo);

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'user_type' => UserType::Student,
                'is_active' => true,
            ]
        );

        if (! $user->hasRole('student')) {
            $user->assignRole('student');
        }

        if ($user->studentProfile) {
            return;
        }

        $profile = $user->studentProfile()->create([
            'roll_no' => $rollNo,
            'current_class_id' => $class->id,
            'current_section_id' => $class->sections->isNotEmpty() ? $class->sections->random()->id : null,
            'current_group_id' => $class->groups->isNotEmpty() ? $class->groups->random()->id : null,
            'session_year' => $sessionYear,
            'gender' => $isMale ? Gender::Male : Gender::Female,
            'nationality' => 'Bangladeshi',
            'status' => StudentStatus::Active,
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
