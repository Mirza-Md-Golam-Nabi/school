<?php

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\ExamType;
use App\Models\Section;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\User;
use App\Support\HmacSignature;
use App\Support\QuestionBankConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    QuestionBankConnection::save('https://qb.test', 'QB-ABCDEFGHIJ', 'the-school-secret');
    QuestionBankConnection::markConnected('Sunrise High School');

    $this->class = Classes::create(['name' => 'Class 9', 'order' => 9, 'has_section' => true, 'is_active' => true]);
    $this->section = Section::create(['class_id' => $this->class->id, 'name' => 'A', 'is_active' => true]);
    $this->subject = Subject::create(['name' => 'Physics', 'name_bn' => 'পদার্থবিজ্ঞান', 'has_mcq' => true, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $this->subject->classes()->attach($this->class->id, ['subject_type' => SubjectType::Compulsory->value]);
    $this->written = Subject::create(['name' => 'Bangla', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $this->written->classes()->attach($this->class->id, ['subject_type' => SubjectType::Compulsory->value]);

    $this->exam = Exam::create([
        'exam_type_id' => ExamType::create(['name' => 'Half Yearly', 'is_active' => true])->id,
        'class_id' => $this->class->id,
        'session_year' => 2026,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'is_published' => false,
    ]);
    ExamSubjectConfig::create(['exam_id' => $this->exam->id, 'subject_id' => $this->subject->id, 'mcq_total' => 30, 'written_total' => 70, 'total_marks' => 100, 'pass_mark' => 33]);
    ExamSubjectConfig::create(['exam_id' => $this->exam->id, 'subject_id' => $this->written->id, 'mcq_total' => 0, 'written_total' => 100, 'total_marks' => 100, 'pass_mark' => 33]);

    $this->student = fn (int $roll, string $name): StudentProfile => StudentProfile::create([
        'user_id' => User::factory()->create(['name' => $name, 'user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => $roll,
        'current_class_id' => $this->class->id,
        'current_section_id' => $this->section->id,
        'session_year' => 2026,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    /** A request the Question Bank signs as it does. */
    $this->asQuestionBank = function (string $method, string $uri, array $body = []) {
        $json = $method === 'GET' ? '' : json_encode($body);
        $timestamp = (string) now()->timestamp;

        return $this->call($method, $uri, [], [], [], [
            'HTTP_X_SCHOOL_CODE' => 'QB-ABCDEFGHIJ',
            'HTTP_X_TIMESTAMP' => $timestamp,
            'HTTP_X_SIGNATURE' => HmacSignature::sign('the-school-secret', $timestamp, $json),
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $json);
    };

    $this->results = "/api/question-bank/exams/{$this->exam->id}/subjects/{$this->subject->id}/results";
});

it('lists a class\'s exams with only their MCQ subjects', function () {
    ($this->asQuestionBank)('GET', "/api/question-bank/classes/{$this->class->id}/exams")
        ->assertOk()
        ->assertJsonPath('exams.0.name', 'Half Yearly - Class 9 (2026)')
        ->assertJsonPath('exams.0.is_published', false)
        ->assertJsonCount(1, 'exams.0.subjects')
        ->assertJsonPath('exams.0.subjects.0.name', 'Physics')
        ->assertJsonPath('exams.0.subjects.0.mcq_total', 30);
});

it('gives the students of a subject with the marks they already have', function () {
    $rahim = ($this->student)(15, 'Rahim Uddin');
    StudentResult::create(['exam_id' => $this->exam->id, 'subject_id' => $this->subject->id, 'class_id' => $this->class->id, 'student_id' => $rahim->id, 'written_marks' => 50]);

    ($this->asQuestionBank)('GET', "/api/question-bank/exams/{$this->exam->id}/subjects/{$this->subject->id}/students")
        ->assertOk()
        ->assertJsonPath('mcq_total', 30)
        ->assertJsonPath('students.0', [
            'id' => $rahim->id, 'roll' => '15', 'name' => 'Rahim Uddin', 'section_id' => $this->section->id, 'section_name' => 'A',
            'mcq_marks' => null, 'is_absent' => false, 'has_other_marks' => true,
        ]);

    // A subject with no MCQ in this exam isn't one marks can be sent to.
    ($this->asQuestionBank)('GET', "/api/question-bank/exams/{$this->exam->id}/subjects/{$this->written->id}/students")->assertNotFound();
});

it('puts the MCQ marks in, and leaves the written marks as they are', function () {
    $rahim = ($this->student)(15, 'Rahim Uddin');
    StudentResult::create(['exam_id' => $this->exam->id, 'subject_id' => $this->subject->id, 'class_id' => $this->class->id, 'student_id' => $rahim->id, 'written_marks' => 50]);

    ($this->asQuestionBank)('POST', $this->results, ['overwrite' => false, 'marks' => [['student_id' => $rahim->id, 'mcq_marks' => 22.5, 'absent' => false]]])
        ->assertOk()
        ->assertExactJson(['saved' => 1, 'skipped' => []]);

    $result = StudentResult::query()->where('student_id', $rahim->id)->sole();

    expect($result->mcq_marks)->toBe(22.5)
        ->and($result->written_marks)->toBe(50.0)
        ->and($result->total_marks)->toBe(72.5);
});

it('gives the same result when the same marks come twice', function () {
    $rahim = ($this->student)(15, 'Rahim Uddin');
    $send = fn () => ($this->asQuestionBank)('POST', $this->results, ['overwrite' => false, 'marks' => [['student_id' => $rahim->id, 'mcq_marks' => 20, 'absent' => false]]]);

    $send()->assertJsonPath('saved', 1);
    $send()->assertJsonPath('saved', 1)->assertJsonPath('skipped', []);

    expect(StudentResult::query()->count())->toBe(1);
});

it('changes marks already there only when told to', function () {
    $rahim = ($this->student)(15, 'Rahim Uddin');
    StudentResult::create(['exam_id' => $this->exam->id, 'subject_id' => $this->subject->id, 'class_id' => $this->class->id, 'student_id' => $rahim->id, 'mcq_marks' => 10]);
    $marks = [['student_id' => $rahim->id, 'mcq_marks' => 25, 'absent' => false]];

    ($this->asQuestionBank)('POST', $this->results, ['overwrite' => false, 'marks' => $marks])
        ->assertJsonPath('skipped.0.reason', 'has_marks');
    expect(StudentResult::query()->sole()->mcq_marks)->toBe(10.0);

    ($this->asQuestionBank)('POST', $this->results, ['overwrite' => true, 'marks' => $marks])->assertJsonPath('saved', 1);
    expect(StudentResult::query()->sole()->mcq_marks)->toBe(25.0);
});

it('marks a student absent only when it would not wipe their other marks', function () {
    [$away, $wroteOnly] = [($this->student)(1, 'Away'), ($this->student)(2, 'Wrote only')];
    StudentResult::create(['exam_id' => $this->exam->id, 'subject_id' => $this->subject->id, 'class_id' => $this->class->id, 'student_id' => $wroteOnly->id, 'written_marks' => 40]);

    ($this->asQuestionBank)('POST', $this->results, ['overwrite' => true, 'marks' => [
        ['student_id' => $away->id, 'mcq_marks' => null, 'absent' => true],
        ['student_id' => $wroteOnly->id, 'mcq_marks' => null, 'absent' => true],
    ]])
        ->assertJsonPath('saved', 1)
        ->assertJsonPath('skipped', [['student_id' => $wroteOnly->id, 'reason' => 'has_other_marks']]);

    expect(StudentResult::query()->where('student_id', $away->id)->sole()->is_absent)->toBeTrue()
        ->and(StudentResult::query()->where('student_id', $wroteOnly->id)->sole()->written_marks)->toBe(40.0);
});

it('refuses marks above the full marks, and students who don\'t take the subject', function () {
    $rahim = ($this->student)(15, 'Rahim Uddin');

    ($this->asQuestionBank)('POST', $this->results, ['overwrite' => false, 'marks' => [
        ['student_id' => $rahim->id, 'mcq_marks' => 31, 'absent' => false],
        ['student_id' => 999999, 'mcq_marks' => 10, 'absent' => false],
    ]])
        ->assertJsonPath('saved', 0)
        ->assertJsonPath('skipped', [
            ['student_id' => $rahim->id, 'reason' => 'over_full_marks'],
            ['student_id' => 999999, 'reason' => 'not_in_subject'],
        ]);
});

it('changes nothing once the exam result is published', function () {
    $rahim = ($this->student)(15, 'Rahim Uddin');
    $this->exam->update(['is_published' => true]);

    ($this->asQuestionBank)('POST', $this->results, ['overwrite' => true, 'marks' => [['student_id' => $rahim->id, 'mcq_marks' => 20, 'absent' => false]]])
        ->assertStatus(409);

    expect(StudentResult::query()->count())->toBe(0);
});

it('answers only the question bank', function () {
    $this->postJson($this->results, ['overwrite' => true, 'marks' => []])->assertUnauthorized();
});
