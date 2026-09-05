<?php

use App\Enums\SubjectType;
use App\Models\Classes;
use App\Models\ClassGroupSubject;
use App\Models\Group;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * main_optional শুধু নির্বাচিত group-এর নিজস্ব optional subject থেকে বাছা যায়
 * — "All Groups" (group_id = null) optional subject main_optional-এ আসে না,
 * কিন্তু extra_optional-এ group-specific + All Groups দুটোই আসে।
 */
it("scopes main_optional options to the selected group's own optional subjects only", function () {
    $class = Classes::create(['name' => 'Class 9', 'order' => 9, 'has_group' => true, 'is_active' => true]);
    $science = Group::create(['name' => 'Science', 'is_active' => true]);
    $commerce = Group::create(['name' => 'Commerce', 'is_active' => true]);
    $class->groups()->attach([$science->id, $commerce->id]);

    $biology = Subject::create(['name' => 'Biology', 'has_written' => true, 'is_active' => true]);
    $accounting = Subject::create(['name' => 'Accounting', 'has_written' => true, 'is_active' => true]);
    $ict = Subject::create(['name' => 'ICT (Additional)', 'has_written' => true, 'is_active' => true]);

    $biology->classes()->attach($class->id, ['group_id' => $science->id, 'subject_type' => SubjectType::Optional->value]);
    $accounting->classes()->attach($class->id, ['group_id' => $commerce->id, 'subject_type' => SubjectType::Optional->value]);
    // "All Groups" optional subject — available to every group, but only as an extra_optional pick.
    $ict->classes()->attach($class->id, ['group_id' => null, 'subject_type' => SubjectType::Optional->value]);

    // Science: main_optional pool has only Biology, not the All-Groups ICT or Commerce's Accounting.
    $scienceMainOptions = ClassGroupSubject::optionalSubjectOptions($class->id, $science->id, includeAllGroups: false);
    expect($scienceMainOptions->keys()->all())->toBe([$biology->id]);

    // Science: extra_optional pool has Biology + the All-Groups ICT, but not Commerce's Accounting.
    $scienceExtraOptions = ClassGroupSubject::optionalSubjectOptions($class->id, $science->id);
    expect($scienceExtraOptions->keys()->sort()->values()->all())->toBe(collect([$biology->id, $ict->id])->sort()->values()->all());

    // Commerce: main_optional pool has only Accounting.
    $commerceMainOptions = ClassGroupSubject::optionalSubjectOptions($class->id, $commerce->id, includeAllGroups: false);
    expect($commerceMainOptions->keys()->all())->toBe([$accounting->id]);

    // Commerce: extra_optional pool has Accounting + the All-Groups ICT, but not Science's Biology.
    $commerceExtraOptions = ClassGroupSubject::optionalSubjectOptions($class->id, $commerce->id);
    expect($commerceExtraOptions->keys()->sort()->values()->all())->toBe(collect([$accounting->id, $ict->id])->sort()->values()->all());
});
