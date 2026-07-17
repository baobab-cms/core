@foreach ($css as $file)
    <link rel="stylesheet" href="{{ $file }}">
@endforeach
@foreach ($js as $file)
    <script type="module" src="{{ $file }}"></script>
@endforeach
