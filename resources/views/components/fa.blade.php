@props(['name', 'family' => null, 'variant' => null, 'mode' => null])
{{ \Unloc\FontAwesome\Facades\FontAwesome::render($name, $family, $variant, $attributes, $mode) }}
