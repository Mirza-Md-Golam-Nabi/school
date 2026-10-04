<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The ready-made student ID card designs a school can choose between. Every
 * design shares the same card size and field positions — what differs is the
 * background artwork, the colour palette and the ring around the portrait.
 */
enum StudentIdCardTemplate: string implements HasLabel
{
    case Royal = 'royal';
    case Emerald = 'emerald';
    case Sunset = 'sunset';
    case Ocean = 'ocean';
    case Midnight = 'midnight';

    public function getLabel(): string
    {
        return match ($this) {
            self::Royal => 'Royal Wave',
            self::Emerald => 'Emerald Prism',
            self::Sunset => 'Sunset Dome',
            self::Ocean => 'Ocean Slant',
            self::Midnight => 'Midnight Gold',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Royal => 'নীল-বেগুনি ঢেউ, সোনালি রেখা',
            self::Emerald => 'সবুজ কৌণিক নকশা, লাইম রেখা',
            self::Sunset => 'কমলা-গোলাপি গম্বুজ, উষ্ণ রং',
            self::Ocean => 'নীল তির্যক ব্লক, কর্পোরেট ধাঁচ',
            self::Midnight => 'গাঢ় পটভূমিতে সোনালি, প্রিমিয়াম',
        };
    }

    /**
     * The Blade view holding this design's SVG artwork for one side of the card.
     *
     * @param  'front'|'back'  $side
     */
    public function backgroundView(string $side): string
    {
        return "documents.student-id-card.{$this->value}.{$side}-background";
    }

    /**
     * The artwork for one side as an inline SVG image, sized to the card.
     *
     * @param  'front'|'back'  $side
     */
    public function backgroundDataUri(string $side, float $width, float $height): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(
            view($this->backgroundView($side), ['width' => $width, 'height' => $height])->render()
        );
    }

    /**
     * RGB of the ring baked around the portrait.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    public function photoRing(): array
    {
        return match ($this) {
            self::Royal, self::Midnight => [245, 185, 66],
            self::Emerald => [163, 230, 53],
            self::Sunset => [251, 191, 36],
            self::Ocean => [34, 211, 238],
        };
    }

    /**
     * Text and accent colours used on top of this design's artwork.
     *
     * @return array{
     *     text: string, schoolName: string, schoolAddress: string, studentName: string,
     *     roleBackground: string, roleText: string, rowBorder: string, label: string, value: string,
     *     footer: string, footerAccent: string, backTitle: string, fieldLabel: string, fieldValue: string,
     *     muted: string, returnNote: string, highlight: string, backHighlight: string
     * }
     */
    public function theme(): array
    {
        return match ($this) {
            self::Royal => [
                'text' => '#1e1b4b',
                'schoolName' => '#ffffff',
                'schoolAddress' => '#c7d2fe',
                'studentName' => '#1e1b4b',
                'roleBackground' => '#3730a3',
                'roleText' => '#ffffff',
                'rowBorder' => '#e0e7ff',
                'label' => '#6366f1',
                'value' => '#1e1b4b',
                'footer' => '#ffffff',
                'footerAccent' => '#fcd34d',
                'backTitle' => '#ffffff',
                'fieldLabel' => '#6366f1',
                'fieldValue' => '#1e1b4b',
                'muted' => '#4b5563',
                'returnNote' => '#6b7280',
                'highlight' => '#dc2626',
                'backHighlight' => '#dc2626',
            ],
            self::Emerald => [
                'text' => '#064e3b',
                'schoolName' => '#ffffff',
                'schoolAddress' => '#d1fae5',
                'studentName' => '#064e3b',
                'roleBackground' => '#047857',
                'roleText' => '#ffffff',
                'rowBorder' => '#d1fae5',
                'label' => '#059669',
                'value' => '#064e3b',
                'footer' => '#ffffff',
                'footerAccent' => '#d9f99d',
                'backTitle' => '#ffffff',
                'fieldLabel' => '#059669',
                'fieldValue' => '#064e3b',
                'muted' => '#4b5563',
                'returnNote' => '#6b7280',
                'highlight' => '#dc2626',
                'backHighlight' => '#dc2626',
            ],
            self::Sunset => [
                'text' => '#4c0519',
                'schoolName' => '#ffffff',
                'schoolAddress' => '#ffedd5',
                'studentName' => '#881337',
                'roleBackground' => '#e11d48',
                'roleText' => '#ffffff',
                'rowBorder' => '#ffe4e6',
                'label' => '#ea580c',
                'value' => '#4c0519',
                'footer' => '#ffffff',
                'footerAccent' => '#fde68a',
                'backTitle' => '#ffffff',
                'fieldLabel' => '#ea580c',
                'fieldValue' => '#4c0519',
                'muted' => '#57534e',
                'returnNote' => '#78716c',
                'highlight' => '#be123c',
                'backHighlight' => '#be123c',
            ],
            self::Ocean => [
                'text' => '#0c4a6e',
                'schoolName' => '#ffffff',
                'schoolAddress' => '#bae6fd',
                'studentName' => '#0c4a6e',
                'roleBackground' => '#0369a1',
                'roleText' => '#ffffff',
                'rowBorder' => '#e0f2fe',
                'label' => '#0284c7',
                'value' => '#0c4a6e',
                'footer' => '#ffffff',
                'footerAccent' => '#67e8f9',
                'backTitle' => '#ffffff',
                'fieldLabel' => '#0284c7',
                'fieldValue' => '#0c4a6e',
                'muted' => '#475569',
                'returnNote' => '#64748b',
                'highlight' => '#dc2626',
                'backHighlight' => '#dc2626',
            ],
            self::Midnight => [
                'text' => '#f8fafc',
                'schoolName' => '#fde68a',
                'schoolAddress' => '#cbd5e1',
                'studentName' => '#ffffff',
                'roleBackground' => '#f59e0b',
                'roleText' => '#0f172a',
                'rowBorder' => '#334155',
                'label' => '#fbbf24',
                'value' => '#f8fafc',
                'footer' => '#0f172a',
                'footerAccent' => '#7c2d12',
                'backTitle' => '#fde68a',
                'fieldLabel' => '#b45309',
                'fieldValue' => '#0f172a',
                'muted' => '#475569',
                'returnNote' => '#64748b',
                'highlight' => '#f87171',
                'backHighlight' => '#dc2626',
            ],
        };
    }
}
