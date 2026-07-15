@props(['name', 'family' => null, 'style' => null])
{{ \Unloc\FontAwesome\Facades\FontAwesome::render($name, $family, $style, $attributes) }}
