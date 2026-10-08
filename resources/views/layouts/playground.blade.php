<!DOCTYPE html>
<html lang="ms">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>{{ $title ?? 'ChessFlow Playground' }}</title>
        @fonts
        @vite(['resources/css/chessflow.css', 'resources/ts/app.ts'])
    </head>
    <body>
        {{ $slot }}
    </body>
</html>
