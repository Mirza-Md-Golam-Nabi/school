<?php

test('a supported locale can be switched and is stored in the session', function () {
    $response = $this->from('/admin')->get('/locale/bn');

    $response->assertRedirect('/admin');
    $this->assertSame('bn', session('locale'));
});

test('switching to an unsupported locale is rejected', function () {
    $response = $this->get('/locale/fr');

    $response->assertNotFound();
});
