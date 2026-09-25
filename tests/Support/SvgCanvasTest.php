<?php

use Unloc\FontAwesome\Support\SvgCanvas;

it('centers a narrow glyph on a square grid', function () {
    expect((new SvgCanvas())->square('<svg viewBox="0 0 448 512"><path/></svg>', '7'))
        ->toBe('<svg viewBox="-32 0 512 512" overflow="visible"><path/></svg>');
});

it('centers a wide glyph on a square grid', function () {
    expect((new SvgCanvas())->square('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path/></svg>', '7'))
        ->toBe('<svg xmlns="http://www.w3.org/2000/svg" viewBox="32 0 512 512" overflow="visible"><path/></svg>');
});

it('keeps fractional offsets', function () {
    expect((new SvgCanvas())->square('<svg viewBox="0 0 381 512"></svg>', '7'))
        ->toBe('<svg viewBox="-65.5 0 512 512" overflow="visible"></svg>');
});

it('keeps a square glyph in place', function () {
    expect((new SvgCanvas())->square('<svg viewBox="0 0 512 512"></svg>', '7'))
        ->toBe('<svg viewBox="0 0 512 512" overflow="visible"></svg>');
});

it('keeps attributes after the viewBox', function () {
    expect((new SvgCanvas())->square('<svg viewBox="0 0 448 512" xmlns="http://www.w3.org/2000/svg"></svg>', '7'))
        ->toBe('<svg viewBox="-32 0 512 512" overflow="visible" xmlns="http://www.w3.org/2000/svg"></svg>');
});

it('is idempotent', function () {
    $canvas = new SvgCanvas();

    expect($canvas->square($canvas->square('<svg viewBox="0 0 512 512"></svg>', '7'), '7'))
        ->toBe('<svg viewBox="0 0 512 512" overflow="visible"></svg>');
});

it('leaves a glyph wider than the canvas alone', function () {
    expect((new SvgCanvas())->square('<svg viewBox="0 0 700 512"></svg>', '7'))
        ->toBe('<svg viewBox="0 0 700 512"></svg>');
});

it('leaves version 6 icons trimmed', function () {
    expect((new SvgCanvas())->square('<svg viewBox="0 0 448 512"></svg>', '6'))
        ->toBe('<svg viewBox="0 0 448 512"></svg>');
});
