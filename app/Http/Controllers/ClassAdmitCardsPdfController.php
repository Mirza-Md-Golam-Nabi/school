<?php

namespace App\Http\Controllers;

use App\Actions\BuildCombinedClassAdmitCardsPdfAction;
use App\Models\AdmitCardBundle;
use App\Models\Classes;
use App\Models\Exam;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ClassAdmitCardsPdfController extends Controller
{
    /**
     * Download one PDF combining every generated admit card for this class + exam,
     * two per row, several rows per A4 page — the stored ready-made bundle when
     * it is up to date, otherwise built right now.
     */
    public function __invoke(Classes $class, Exam $exam, BuildCombinedClassAdmitCardsPdfAction $action): Response
    {
        $fileName = "admit-cards-{$class->id}-{$exam->id}.pdf";

        if ($exam->class_id === $class->id) {
            $bundle = AdmitCardBundle::where('exam_id', $exam->id)->first();

            if ($bundle?->isReadyFor($action->admitCardsQuery($class->id, $exam)->count())) {
                return Storage::disk('local')->download($bundle->file_path, $fileName, ['Content-Type' => 'application/pdf']);
            }
        }

        return response($action->handle($class, $exam), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }
}
