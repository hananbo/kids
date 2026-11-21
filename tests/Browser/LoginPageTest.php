<?php

test('the login page renders in the browser without javascript errors', function () {
    visit(route('login'))
        ->assertSee('Log in')
        ->assertNoJavaScriptErrors();
});
