@props(['name', 'family' => null, 'variant' => null, 'mode' => null, 'version' => null])
{{ \Unloc\FontAwesome\Facades\FontAwesome::render($name, $family, $variant, $attributes, $mode, $version) }}
