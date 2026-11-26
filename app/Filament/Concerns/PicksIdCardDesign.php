<?php

namespace App\Filament\Concerns;

use App\Enums\StudentIdCardTemplate;
use App\Models\SchoolSetting;
use App\Support\IdCardLogo;
use App\Support\StaffIdCardLayout;
use App\Support\StudentIdCardLayout;
use App\Support\TeacherIdCardLayout;
use Filament\Notifications\Notification;

/**
 * The design picker shared by the student, teacher and staff ID card pages. Clicking
 * a design or changing the logo colour only updates the preview; nothing is
 * saved — and no cards are made — until the school submits the design.
 */
trait PicksIdCardDesign
{
    public string $draftTemplate = '';

    /**
     * Whether the logo is printed in $draftLogoColour rather than as uploaded.
     */
    public bool $recolourLogo = false;

    public string $draftLogoColour = '#ffffff';

    /**
     * The layout class holding this card type's saved design.
     *
     * @return class-string<StudentIdCardLayout|TeacherIdCardLayout|StaffIdCardLayout>
     */
    abstract protected function idCardLayout(): string;

    public function mountPicksIdCardDesign(): void
    {
        $layout = $this->idCardLayout();

        $this->draftTemplate = $layout::template()->value;
        $this->recolourLogo = $layout::logoColour() !== null;
        $this->draftLogoColour = $layout::logoColour() ?? '#ffffff';
    }

    /**
     * Preview the design the school clicked — it is saved only on submit.
     */
    public function selectTemplate(string $template): void
    {
        if (StudentIdCardTemplate::tryFrom($template) !== null) {
            $this->draftTemplate = $template;
        }
    }

    /**
     * Save the previewed design and logo colour; cards are made in them from now on.
     */
    public function submitDesign(): void
    {
        $this->validate([
            'draftTemplate' => ['required', 'string'],
            'draftLogoColour' => ['required', 'regex:'.IdCardLogo::COLOUR_PATTERN],
        ], [
            'draftLogoColour.regex' => 'লোগোর রং #RRGGBB আকারে দিন।',
        ]);

        $template = StudentIdCardTemplate::tryFrom($this->draftTemplate);

        if ($template === null) {
            return;
        }

        $this->idCardLayout()::submitDesign($template, $this->recolourLogo ? $this->draftLogoColour : null);

        Notification::make()
            ->title("\"{$template->getLabel()}\" ডিজাইন সাবমিট হয়েছে।")
            ->body('এখন থেকে ID card এই ডিজাইনেই তৈরি হবে।')
            ->success()
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    protected function designPickerViewData(float $cardWidth, float $cardHeight): array
    {
        $layout = $this->idCardLayout();
        $savedLogoColour = $layout::logoColour();
        $draftLogoColour = $this->recolourLogo && IdCardLogo::isValidColour($this->draftLogoColour) ? $this->draftLogoColour : null;

        return [
            'selectedTemplate' => $layout::template()->value,
            'draftTemplate' => $this->draftTemplate,
            'hasSubmittedDesign' => $layout::hasSubmittedDesign(),
            'hasUnsubmittedChanges' => ! $layout::hasSubmittedDesign()
                || $this->draftTemplate !== $layout::template()->value
                || $draftLogoColour !== $savedLogoColour,
            'hasSchoolLogo' => IdCardLogo::path() !== null,
            'previewLogo' => IdCardLogo::dataUri($draftLogoColour),
            'schoolName' => (string) SchoolSetting::get('school_name', 'School Name'),
            'schoolAddress' => (string) SchoolSetting::get('school_address', ''),
            'templates' => array_map(fn (StudentIdCardTemplate $template): array => [
                'value' => $template->value,
                'label' => $template->getLabel(),
                'description' => $template->description(),
                'theme' => $template->theme(),
                'ring' => $template->photoRing(),
                'front' => $template->backgroundDataUri('front', $cardWidth, $cardHeight),
                'back' => $template->backgroundDataUri('back', $cardWidth, $cardHeight),
            ], StudentIdCardTemplate::cases()),
        ];
    }
}
