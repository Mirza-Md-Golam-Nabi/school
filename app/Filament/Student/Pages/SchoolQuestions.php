<?php

namespace App\Filament\Student\Pages;

use App\Support\QuestionBankConnection;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * স্কুলের নিজের প্রশ্ন — the questions this school wrote in the Question
 * Bank for its exams, with the answers, once the exams they were on are
 * over. The Question Bank decides what is open and shows it; this page
 * opens it in a frame through a short-lived link signed with the school's
 * secret. Only there while the school is connected.
 */
class SchoolQuestions extends Page
{
    protected string $view = 'filament.student.pages.school-questions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static ?int $navigationSort = 89;

    public static function canAccess(): bool
    {
        return QuestionBankConnection::isConnected();
    }

    public static function getNavigationLabel(): string
    {
        return __('School questions');
    }

    public function getTitle(): string
    {
        return __('School questions');
    }

    public function questionsUrl(): ?string
    {
        return QuestionBankConnection::schoolQuestionsUrl(app()->getLocale());
    }
}
