<?php

use Unloc\FontAwesome\Support\IconReference;

it('builds a slash-separated key', function () {
    $ref = new IconReference('gear', 'classic', 'solid');
    expect($ref->key())->toBe('classic/solid/gear');
});
