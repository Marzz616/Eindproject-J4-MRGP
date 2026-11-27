<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Voedselbank Maaskantje</title>
  <link rel="icon" href="/favicon.ico" sizes="any">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Zilla+Slab:wght@600;700&family=Source+Sans+3:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">

  {{-- Laadt de Vue-app en de stijlen die Vite heeft gebouwd. --}}
  @vite('resources/js/app.js')
</head>
<body>
  {{-- Hier hangt Vue de hele back-office in; welk scherm je ziet bepaalt de Vue-router. --}}
  <div id="app"></div>
</body>
</html>
