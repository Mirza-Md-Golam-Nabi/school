<?php

use App\Filament\Student\Pages\Auth\EditProfile;

it('renders the student name field as disabled and read-only', function () {
    $page = new EditProfile;

    $component = (new ReflectionMethod($page, 'getNameFormComponent'))->invoke($page);

    expect($component->isDisabled())->toBeTrue()
        ->and($component->isDehydrated())->toBeFalse();
});
