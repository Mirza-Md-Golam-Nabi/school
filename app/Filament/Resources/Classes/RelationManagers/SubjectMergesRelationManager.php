<?php

namespace App\Filament\Resources\Classes\RelationManagers;

use App\Filament\Resources\Concerns\SubjectMergeFields;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Table;

/**
 * The class's default subject merges (e.g. Bangla 1st + 2nd paper = Bangla),
 * kept in step on every exam of the class that still follows the default.
 */
class SubjectMergesRelationManager extends RelationManager
{
    protected static string $relationship = 'subjectMerges';

    protected static ?string $title = 'Merged Subjects';

    public function form(Schema $schema): Schema
    {
        $class = $this->getOwnerRecord();

        return $schema
            ->columns(1)
            ->components(SubjectMergeFields::form($class->id, fn () => $class->subjectMerges(), rulesRequired: false));
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('এই ক্লাসের সব পরীক্ষায় এই মার্জগুলো নিজে থেকেই বসে, আর এখানে বদলালে পরীক্ষাগুলোতেও বদলায়। শুধু প্রকাশিত রেজাল্ট, আর যে পরীক্ষায় নিজে মার্জ বদলানো হয়েছে — সেগুলো বদলায় না।')
            ->columns(SubjectMergeFields::columns())
            ->headerActions([
                CreateAction::make()
                    ->label('Merge Subjects')
                    ->modalWidth(Width::TwoExtraLarge),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->modalWidth(Width::TwoExtraLarge),
                DeleteAction::make()->iconButton(),
            ])
            ->emptyStateHeading('No merged subjects')
            ->emptyStateDescription('যেমন: বাংলা ১ম পত্র + বাংলা ২য় পত্র = বাংলা');
    }
}
