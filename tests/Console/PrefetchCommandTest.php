<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config()->set('fontawesome.disk', 'local');
    Http::fake(['api.fontawesome.com' => Http::response(['data' => ['release' => ['icon' => ['svgs' => [['html' => '<svg/>']]]]]])]);
});

it('prefetches config entries and scanned static usages, skipping dynamic', function () {
    $views = sys_get_temp_dir() . '/fa-views-' . uniqid();
    mkdir($views);
    file_put_contents($views . '/page.blade.php', '<x-fa name="gear" /> <x-fa  name = "user" family="sharp" /> <x-fa :name="$x" />');

    config()->set('fontawesome.scan_paths', [$views]);
    config()->set('fontawesome.prefetch', ['heart', ['name' => 'star', 'family' => 'sharp', 'style' => 'solid']]);

    $this->artisan('fontawesome:prefetch')->assertSuccessful();

    Storage::disk('local')->assertExists('fontawesome/7/classic/solid/gear.svg');
    Storage::disk('local')->assertExists('fontawesome/7/sharp/solid/user.svg');
    Storage::disk('local')->assertExists('fontawesome/7/classic/solid/heart.svg');
    Storage::disk('local')->assertExists('fontawesome/7/sharp/solid/star.svg');
});
