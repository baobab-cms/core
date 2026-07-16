<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
</head>
<body>
    <h1>{{ $title }}</h1>
    <ul>
        @foreach ($entries as $entry)
            <li>{{ $entry->getAttribute('slug') }}</li>
        @endforeach
    </ul>
    {{ $entries->links() }}
</body>
</html>
