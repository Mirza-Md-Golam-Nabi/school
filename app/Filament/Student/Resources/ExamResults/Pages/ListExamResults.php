<?php

namespace App\Filament\Student\Resources\ExamResults\Pages;

use App\Filament\Student\Resources\ExamResults\ExamResultResource;
use Filament\Resources\Pages\ListRecords;

class ListExamResults extends ListRecords
{
    protected static string $resource = ExamResultResource::class;
}
