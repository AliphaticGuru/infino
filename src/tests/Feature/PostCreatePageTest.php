<?php

test('post create page renders successfully', function () {
    $response = $this->get(route('post.create'));

    $response->assertOk();
});
