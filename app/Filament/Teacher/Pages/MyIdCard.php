<?php

namespace App\Filament\Teacher\Pages;

use App\Actions\BuildTeacherIdCardsPdfAction;
use App\Enums\TeacherIdCardField;
use App\Models\SchoolSetting;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Support\IdCardLogo;
use App\Support\IdCardPhotoPosition;
use App\Support\QrCodeImage;
use App\Support\TeacherIdCardLayout;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

/**
 * Shows the teacher how their ID card will look and lets them drag and zoom
 * their profile photo inside the round frame, then save that placement for
 * every card printed from now on.
 */
class MyIdCard extends Page
{
    protected string $view = 'filament.teacher.pages.my-id-card';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $navigationLabel = 'My ID Card';

    protected static ?string $title = 'My ID Card';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user?->teacherProfile !== null;
    }

    public function savePhotoPosition(float $x, float $y, float $zoom): void
    {
        $position = new IdCardPhotoPosition($x, $y, $zoom);

        $this->teacherProfile()->update(['id_card_photo_position' => $position->toArray()]);

        Notification::make()
            ->title('ছবির পজিশন সেভ হয়েছে।')
            ->body('এখন থেকে আপনার ID কার্ড এই পজিশনেই ছাপা হবে।')
            ->success()
            ->send();
    }

    public function resetPhotoPosition(): void
    {
        $this->teacherProfile()->update(['id_card_photo_position' => null]);

        Notification::make()
            ->title('ছবির পজিশন ডিফল্টে ফিরে গেছে।')
            ->success()
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $teacher = $this->teacherProfile()->load(['user', 'addresses']);
        $template = TeacherIdCardLayout::template();
        $width = BuildTeacherIdCardsPdfAction::CARD_WIDTH;
        $height = BuildTeacherIdCardsPdfAction::CARD_HEIGHT;

        $resolve = fn (array $fields): array => collect($fields)
            ->map(fn (TeacherIdCardField $field): array => [
                'field' => $field,
                'shortLabel' => $field->shortLabel(),
                'label' => mb_strtoupper($field->getLabel()),
                'value' => $field->valueFor($teacher),
            ])
            ->filter(fn (array $detail): bool => $detail['value'] !== null)
            ->values()
            ->all();

        return [
            'teacher' => $teacher,
            'photoUrl' => $teacher->user?->getFilamentAvatarUrl(),
            'position' => $teacher->idCardPhotoPosition(),
            'theme' => $template->theme(),
            'ring' => 'rgb('.implode(',', $template->photoRing()).')',
            'front' => $template->backgroundDataUri('front', $width, $height),
            'back' => $template->backgroundDataUri('back', $width, $height),
            'cardWidth' => $width,
            'cardHeight' => $height,
            'frontDetails' => $resolve(TeacherIdCardLayout::frontFields()),
            'backDetails' => $resolve(TeacherIdCardLayout::backFields()),
            'issueDate' => TeacherIdCardLayout::issueDate(),
            'qrImage' => TeacherIdCardLayout::showsQrCode() && ($verificationUrl = $teacher->idCardVerificationUrl()) !== null
                ? QrCodeImage::svgDataUri($verificationUrl)
                : null,
            'schoolName' => (string) SchoolSetting::get('school_name', ''),
            'schoolAddress' => (string) SchoolSetting::get('school_address', ''),
            'schoolLogoUrl' => IdCardLogo::dataUri(TeacherIdCardLayout::logoColour()),
            'principalSignatureUrl' => $this->publicUrl(SchoolSetting::get('principal_signature')),
            'signatoryTitle' => SchoolSetting::headSignatoryTitle(),
        ];
    }

    private function publicUrl(mixed $path): ?string
    {
        $storage = Storage::disk('public');

        return (is_string($path) && $path !== '' && $storage->exists($path)) ? $storage->url($path) : null;
    }

    private function teacherProfile(): TeacherProfile
    {
        /** @var User $user */
        $user = auth()->user();

        $profile = $user->teacherProfile;

        abort_if($profile === null, 403);

        return $profile;
    }
}
