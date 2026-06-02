<?php

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertStatus(20 0);
});