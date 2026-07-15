<?php

it('loads package config', function () {
    expect(config('fontawesome.version'))->toBe(7)
        ->and(config('fontawesome.defaults.family'))->toBe('classic');
});
