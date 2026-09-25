<?php

use Unloc\FontAwesome\Support\SvgCanvas;

it('centers a narrow glyph on the square canvas', function () {
    expect((new SvgCanvas(7))->square('<svg viewBox="0 0 448 512"><path/></svg>'))
        ->toBe('<svg viewBox="-96 -64 640 640"><path/></svg>');
});

it('centers a wide glyph on the square canvas', function () {
    expect((new SvgCanvas(7))->square('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path/></svg>'))
        ->toBe('<svg xmlns="http://www.w3.org/2000/svg" viewBox="-32 -64 640 640"><path/></svg>');
});

it('keeps fractional offsets', function () {
    expect((new SvgCanvas(7))->square('<svg viewBox="0 0 381 512"></svg>'))
        ->toBe('<svg viewBox="-129.5 -64 640 640"></svg>');
});

it('fills the canvas exactly at full width', function () {
    expect((new SvgCanvas(7))->square('<svg viewBox="0 0 640 512"></svg>'))
        ->toBe('<svg viewBox="0 -64 640 640"></svg>');
});

it('is idempotent', function () {
    $canvas = new SvgCanvas(7);

    expect($canvas->square($canvas->square('<svg viewBox="0 0 448 512"></svg>')))
        ->toBe('<svg viewBox="-96 -64 640 640"></svg>');
});

it('leaves a glyph wider than the canvas alone', function () {
    expect((new SvgCanvas(7))->square('<svg viewBox="0 0 700 512"></svg>'))
        ->toBe('<svg viewBox="0 0 700 512"></svg>');
});

it('leaves version 6 icons trimmed', function () {
    expect((new SvgCanvas(6))->square('<svg viewBox="0 0 448 512"></svg>'))
        ->toBe('<svg viewBox="0 0 448 512"></svg>');
});
