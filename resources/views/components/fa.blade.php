@props(['name', 'family' => null, 'variant' => null])
{{ \Unloc\FontAwesome\Facades\FontAwesome::render($name, $family, $variant, $attributes) }}
