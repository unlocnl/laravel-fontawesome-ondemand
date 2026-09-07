<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config()->set('fontawesome.disk', 'local');
    Http::fake(function ($request) {
        $variables = json_decode($request->body(), true)['variables'] ?? [];
        $release = [];
        foreach ($variables as $key => $value) {
            if (str_starts_with($key, 'name')) {
                $release['i' . substr($key, 4)] = ['svgs' => [['html' => '<svg/>']]];
            }
        }

        return Http::response(['data' => ['release' => $release]]);
    });
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

it('warms every icon in a single request', function () {
    config()->set('fontawesome.prefetch', ['gear', 'user', 'heart', 'star', 'house', 'bell']);

    $this->artisan('fontawesome:prefetch')->assertSuccessful();

    Http::assertSentCount(1);

    foreach (['gear', 'user', 'heart', 'star', 'house', 'bell'] as $name) {
        Storage::disk('local')->assertExists("fontawesome/7/classic/solid/{$name}.svg");
    }
});
