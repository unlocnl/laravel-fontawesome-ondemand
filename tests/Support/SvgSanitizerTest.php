<?php

use Unloc\FontAwesome\Support\SvgSanitizer;

it('strips the font awesome comment', function () {
    $svg = '<svg viewBox="0 0 1 1"><!--!Font Awesome Free--><path d="M0 0"/></svg>';
    expect((new SvgSanitizer(stripComments: true))->sanitize($svg))
        ->toBe('<svg viewBox="0 0 1 1"><path d="M0 0"/></svg>');
});

it('keeps the comment when disabled', function () {
    $svg = '<svg><!--!x--></svg>';
    expect((new SvgSanitizer(stripComments: false))->sanitize($svg))->toContain('<!--!x-->');
});

it('removes wildcard attributes', function () {
    $svg = '<svg data-foo="a" data-bar="b" viewBox="0 0 1 1"></svg>';
    expect((new SvgSanitizer(removeAttributes: ['data-*']))->sanitize($svg))
        ->toBe('<svg viewBox="0 0 1 1"></svg>');
});
