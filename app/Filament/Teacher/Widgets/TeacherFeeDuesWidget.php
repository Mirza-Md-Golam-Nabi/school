<?php

namespace App\Filament\Teacher\Widgets;

use App\Filament\Teacher\Resources\StudentFeeInvoices\StudentFeeInvoiceResource;
use App\Models\FeePayment;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class TeacherFeeDuesWidget extends Widget
{
    protected string $view = 'filament.teacher.widgets.fee-dues';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 1,
    ];

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return StudentFeeInvoiceResource::canViewAny()
            && (Auth::user()?->teacherProfile?->classesAsClassTeacher()->exists() ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $classIds = Auth::user()->teacherProfile
            ->classesAsClassTeacher()
            ->pluck('id');

        $studentIds = StudentProfile::whereIn('current_class_id', $classIds)->pluck('id');

        $payableInvoiceIds = StudentFeeInvoice::payable()
            ->whereIn('student_id', $studentIds)
            ->pluck('id');

        $netDue = (float) StudentFeeInvoice::whereIn('id', $payableInvoiceIds)->sum('net_amount');
        $paid = (float) FeePayment::whereIn('invoice_id', $payableInvoiceIds)->sum('amount_paid');

        $studentsWithDues = StudentFeeInvoice::payable()
            ->whereIn('student_id', $studentIds)
            ->distinct('student_id')
            ->count('student_id');

        return [
            'totalDue' => max(0, $netDue - $paid),
            'studentsWithDues' => $studentsWithDues,
            'url' => StudentFeeInvoiceResource::getUrl('index', panel: 'teacher'),
        ];
    }
}
