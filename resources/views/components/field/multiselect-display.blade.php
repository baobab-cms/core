@props(['value' => []])

@foreach ($value as $item)
    {{ $item }}{{ $loop->last ? '' : ', ' }}
@endforeach
