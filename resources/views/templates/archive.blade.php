<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <x-baobab::seo-head />
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
