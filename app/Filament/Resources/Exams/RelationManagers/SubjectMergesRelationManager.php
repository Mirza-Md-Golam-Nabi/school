<?php

namespace App\Filament\Resources\Exams\RelationManagers;

use App\Actions\CopySubjectMergesToExamAction;
use App\Filament\Resources\Concerns\SubjectMergeFields;
use App\Models\Exam;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * This exam's own subject merges — copied from the class when the exam was
 * created, changeable here until the result is published.
 */
class SubjectMergesRelationManager extends RelationManager
{
    protected static string $relationship = 'subjectMerges';

    protected static ?string $title = 'Merged Subjects';

    public function form(Schema $schema): Schema
    {
        /** @var Exam $exam */
        $exam = $this->getOwnerRecord();

        return $schema
            ->columns(1)
            ->components(SubjectMergeFields::form($exam->class_id, fn () => $exam->subjectMerges(), rulesRequired: true));
    }

    public function table(Table $table): Table
    {
        $isEditable = fn (): bool => ! $this->getOwnerRecord()->is_published;

        return $table
            ->description('রেজাল্টে এই সাবজেক্টগুলো দুই পত্র মিলে একটা বিষয় হিসেবে গোনা হবে। বদলানোর পর Calculate Rankings আবার চালান। রেজাল্ট প্রকাশের পর বদলানো যায় না।')
            ->columns(SubjectMergeFields::columns())
            ->headerActions([
                Action::make('applyClassDefault')
                    ->label('Apply Class Default')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('gray')
                    ->visible($isEditable)
                    ->requiresConfirmation()
                    ->modalHeading('ক্লাসের ডিফল্ট মার্জ বসাবেন?')
                    ->modalDescription('এই পরীক্ষার এখনকার সব মার্জ মুছে ক্লাসের ডিফল্ট মার্জগুলো বসবে।')
                    ->action(function (): void {
                        $count = app(CopySubjectMergesToExamAction::class)->handle($this->getOwnerRecord());

                        Notification::make()
                            ->title("ক্লাসের {$count}টা মার্জ বসানো হয়েছে।")
                            ->success()
                            ->send();
                    }),
                CreateAction::make()
                    ->label('Merge Subjects')
                    ->visible($isEditable)
                    ->modalWidth(Width::TwoExtraLarge),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->visible($isEditable)
                    ->modalWidth(Width::TwoExtraLarge),
                DeleteAction::make()
                    ->iconButton()
                    ->visible($isEditable),
            ])
            ->emptyStateHeading('No merged subjects')
            ->emptyStateDescription('যেমন: বাংলা ১ম পত্র + বাংলা ২য় পত্র = বাংলা');
    }
}
