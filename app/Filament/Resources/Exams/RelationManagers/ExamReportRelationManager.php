<?php

namespace App\Filament\Resources\Exams\RelationManagers;

use App\Actions\BuildExamResultReport;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View as ViewComponent;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ExamReportRelationManager extends RelationManager
{
    protected static string $relationship = 'meritRankings';

    protected static ?string $title = 'Exam Report';

    /**
     * Only the summary report is shown on this tab — no record table.
     */
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Exam Report')
                    ->description('GPA অনুযায়ী কতজন, কতজন পাস/ফেল, এবং কতজন কয়টি বিষয়ে ফেল করেছে; সাথে পাশ ও ফেল করা student-দের তালিকা।')
                    ->afterHeader([
                        Action::make('downloadExamReport')
                            ->label('Download Report (PDF)')
                            ->icon(Heroicon::OutlinedArrowDownTray)
                            ->color('info')
                            ->visible(fn (): bool => $this->getOwnerRecord()->meritRankings()->exists())
                            ->url(fn (): string => route('exams.result-report.download', ['exam' => $this->getOwnerRecord()])),
                    ])
                    ->schema([
                        ViewComponent::make('filament.shared.exam-result-report')
                            ->viewData(fn (): array => app(BuildExamResultReport::class)->handle($this->getOwnerRecord())),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table->paginated(false);
    }
}
