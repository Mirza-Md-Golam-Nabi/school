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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StaffSeeder extends Seeder
{
    /** @var array<int, string> */
    private array $maleNames = [
        'Md. Jasim Uddin', 'Md. Nurul Haque', 'Md. Iqbal Hossain',
    ];

    /** @var array<int, string> */
    private array $femaleNames = [
        'Mst. Halima Khatun', 'Suraiya Akter', 'Nazia Sultana',
    ];

    /** @var array<int, string> */
    private array $designations = [
        'Accountant', 'Librarian', 'Office Assistant',
        'Lab Assistant', 'Security Guard', 'Store Keeper',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = UserType::Staff->value.'@example.com';
        $user = User::where('email', $email)->first();
        $user->staffProfile()->firstOrCreate([], [
            'gender' => Gender::Male,
        ]);

        $staffMembers = collect($this->maleNames)->map(fn (string $name) => ['name' => $name, 'is_male' => true])
            ->merge(collect($this->femaleNames)->map(fn (string $name) => ['name' => $name, 'is_male' => false]))
            ->shuffle()
            ->values();

        foreach ($staffMembers as $staff) {
            $this->createStaff($staff['name'], $staff['is_male']);
        }
    }

    private function createStaff(string $name, bool $isMale): void
    {
        $email = $this->emailFromName($name);

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'user_type' => UserType::Staff,
                'is_active' => true,
            ]
        );

        if (! $user->hasRole('staff')) {
            $user->assignRole('staff');
        }

        if ($user->staffProfile) {
            return;
        }

        $profile = $user->staffProfile()->create([
            'gender' => $isMale ? Gender::Male : Gender::Female,
            'religion' => Religion::Muslim,
            'nationality' => 'Bangladeshi',
            'designation' => fake()->randomElement($this->designations),
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
            ->replace(['Md.', 'Mst.'], '')
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
