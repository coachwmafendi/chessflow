<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>{{ $title ?? 'ChessFlow Playground' }}</title>
        @include('partials.theme-init')
        @fonts
        @vite(['resources/css/chessflow.css', 'resources/ts/app.ts'])
    </head>
    <body>
        {{ $slot }}
    </body>
</html>
