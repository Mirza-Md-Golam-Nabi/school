<?php

namespace Database\Seeders;

use App\Models\SchoolSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DocumentSettingsSeeder extends Seeder
{
    /**
     * Keys used for generated documents (admit cards, and later marksheets /
     * transfer certificates / ID cards). Reuses the existing school_settings
     * key-value table instead of a separate document_settings table.
     *
     * @var array<string, array{value: string, description: string}>
     */
    protected array $settings = [
        // Shared branding, reused across every document type.
        'school_logo' => [
            'value' => '',
            'description' => 'Storage path (public disk) of the school logo image, used on generated documents.',
        ],
        'school_address' => [
            'value' => '',
            'description' => 'School address printed on generated documents.',
        ],
        'school_established_year' => [
            'value' => '',
            'description' => 'Year the school was established.',
        ],
        'school_seal' => [
            'value' => '',
            'description' => 'Storage path (public disk) of the school seal/stamp image, used on generated documents.',
        ],
        'principal_signature' => [
            'value' => '',
            'description' => 'Storage path (public disk) of the principal\'s signature image, used on generated documents.',
        ],

        // Admit card specific toggles.
        'admit_card_use_watermark' => [
            'value' => '0',
            'description' => 'Whether admit cards render a background watermark ("1" or "0").',
        ],
        'admit_card_watermark_text' => [
            'value' => '',
            'description' => 'Watermark text printed on admit cards when admit_card_use_watermark is enabled.',
        ],
        'admit_card_use_logo' => [
            'value' => '1',
            'description' => 'Whether the school logo is printed on admit cards ("1" or "0").',
        ],
        'admit_card_footer_text' => [
            'value' => '',
            'description' => 'Footer text printed at the bottom of admit cards.',
        ],

        // Marksheet specific toggles.
        'marksheet_use_watermark' => [
            'value' => '0',
            'description' => 'Whether marksheets render a background watermark ("1" or "0").',
        ],
        'marksheet_watermark_text' => [
            'value' => '',
            'description' => 'Watermark text printed on marksheets when marksheet_use_watermark is enabled.',
        ],
        'marksheet_use_logo' => [
            'value' => '1',
            'description' => 'Whether the school logo is printed on marksheets ("1" or "0").',
        ],
        'marksheet_footer_text' => [
            'value' => '',
            'description' => 'Footer text printed at the bottom of marksheets.',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->settings as $key => $setting) {
            $model = SchoolSetting::query()->firstOrNew(['key' => $key]);

            if (! $model->exists) {
                $model->value = $setting['value'];
            }

            $model->description = $setting['description'];
            $model->save();
        }
    }
}
