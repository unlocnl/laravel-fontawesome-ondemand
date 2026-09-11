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

it('prefetches fa-prefetch markers in any file, filling missing parts from the right with defaults', function () {
    $views = sys_get_temp_dir() . '/fa-views-' . uniqid();
    $app = sys_get_temp_dir() . '/fa-app-' . uniqid();
    $js = sys_get_temp_dir() . '/fa-js-' . uniqid();
    mkdir($views);
    mkdir($app);
    mkdir($js);
    file_put_contents($views . '/page.blade.php', '{{-- fa-prefetch/duotone/light/spinner-third --}} <!-- fa-prefetch/bell -->');
    file_put_contents($app . '/Status.php', "<?php\n// fa-prefetch/regular/stroopwafel fa-prefetch/angle-right\n# fa-prefetch/a/b/c/too-deep\n");
    file_put_contents($js . '/icons.js', '/* fa-prefetch/sharp/solid/house */');

    config()->set('fontawesome.scan_paths', [$views, $js]);
    $this->app->useAppPath($app);

    $this->artisan('fontawesome:prefetch')->assertSuccessful();

    Storage::disk('local')->assertExists('fontawesome/7/duotone/light/spinner-third.svg');
    Storage::disk('local')->assertExists('fontawesome/7/classic/solid/bell.svg');
    Storage::disk('local')->assertExists('fontawesome/7/classic/regular/stroopwafel.svg');
    Storage::disk('local')->assertExists('fontawesome/7/classic/solid/angle-right.svg');
    Storage::disk('local')->assertExists('fontawesome/7/sharp/solid/house.svg');
    expect(Storage::disk('local')->allFiles('fontawesome/7/b'))->toBe([]);
});

it('warms every icon in a single request', function () {
    config()->set('fontawesome.prefetch', ['gear', 'user', 'heart', 'star', 'house', 'bell']);

    $this->artisan('fontawesome:prefetch')->assertSuccessful();

    Http::assertSentCount(1);

    foreach (['gear', 'user', 'heart', 'star', 'house', 'bell'] as $name) {
        Storage::disk('local')->assertExists("fontawesome/7/classic/solid/{$name}.svg");
    }
});
