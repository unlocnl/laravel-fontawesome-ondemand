<?php

use Unloc\FontAwesome\Support\FilesystemIconSource;

function iconDir(): string
{
    $dir = sys_get_temp_dir() . '/fa-custom-' . bin2hex(random_bytes(4));
    mkdir($dir . '/regular', 0777, true);
    file_put_contents($dir . '/logo.svg', '<svg>root</svg>');
    file_put_contents($dir . '/regular/logo.svg', '<svg>regular</svg>');
    file_put_contents(dirname($dir) . '/fa-secret.svg', '<svg>secret</svg>');

    return $dir;
}

it('prefers the style subfolder over the root file', function () {
    $source = new FilesystemIconSource(iconDir());

    expect($source->get('logo', 'regular'))->toBe('<svg>regular</svg>');
});

it('falls back to the root file for a style without a subfolder', function () {
    $source = new FilesystemIconSource(iconDir());

    expect($source->get('logo', 'solid'))->toBe('<svg>root</svg>');
});

it('returns null on a miss', function () {
    expect((new FilesystemIconSource(iconDir()))->get('nope', 'solid'))->toBeNull();
});

it('returns null when no path is configured', function () {
    expect((new FilesystemIconSource(null))->get('logo', 'solid'))->toBeNull();
});

it('rejects names that escape the configured directory', function (string $name) {
    expect((new FilesystemIconSource(iconDir()))->get($name, 'solid'))->toBeNull();
})->with(['../fa-secret', 'regular/logo', '/etc/passwd', '.hidden', '']);
