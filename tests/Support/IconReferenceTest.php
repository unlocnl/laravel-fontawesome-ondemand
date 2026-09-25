<?php

use Unloc\FontAwesome\Support\IconReference;

it('builds a slash-separated key', function () {
    $ref = new IconReference('gear', 'classic', 'solid', '7');
    expect($ref->key())->toBe('7/classic/solid/gear');
});
