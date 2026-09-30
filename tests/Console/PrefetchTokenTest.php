<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config()->set('fontawesome.disk', 'local');
    config()->set('fontawesome.prefetch', []);
    config()->set('fontawesome.scan_paths', []);
    $this->app->useAppPath(sys_get_temp_dir() . '/fa-empty-' . uniqid());
});

function fakeTokenExchange(mixed $response): void
{
    Http::fake(['api.fontawesome.com/token' => $response]);
}

it('confirms a token with pro access', function () {
    config()->set('fontawesome.api_token', 'API_TOKEN');
    fakeTokenExchange(Http::response(['access_token' => 'ACCESS', 'scopes' => ['public', 'svg_icons_pro']]));

    $this->artisan('fontawesome:prefetch')
        ->expectsOutput('Font Awesome API token verified: Pro access.')
        ->assertSuccessful();
});

it('warns when the token has no pro access', function () {
    config()->set('fontawesome.api_token', 'API_TOKEN');
    fakeTokenExchange(Http::response(['access_token' => 'ACCESS', 'scopes' => ['public']]));

    $this->artisan('fontawesome:prefetch')
        ->expectsOutput('Font Awesome API token works but has no Pro access: free icons only.')
        ->assertSuccessful();
});

it('reports a rejected token without failing', function () {
    config()->set('fontawesome.api_token', 'API_TOKEN');
    fakeTokenExchange(Http::response('nope', 401));

    $this->artisan('fontawesome:prefetch')
        ->expectsOutputToContain('Font Awesome API token rejected: HTTP 401')
        ->assertSuccessful();
});

it('warns when no token is configured', function () {
    config()->set('fontawesome.api_token', null);
    Http::fake();

    $this->artisan('fontawesome:prefetch')
        ->expectsOutputToContain('No Font Awesome API token configured')
        ->assertSuccessful();

    Http::assertNothingSent();
});

it('skips the token check on the cdn source', function () {
    config()->set('fontawesome.source', 'cdn');
    config()->set('fontawesome.api_token', 'API_TOKEN');
    Http::fake();

    $this->artisan('fontawesome:prefetch')
        ->expectsOutputToContain('API token unused')
        ->assertSuccessful();

    Http::assertNothingSent();
});
