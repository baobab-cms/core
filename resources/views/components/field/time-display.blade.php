@props(['value'])

{{ $value === null ? null : substr($value, 0, 5) }}
