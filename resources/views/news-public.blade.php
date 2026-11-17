<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $news->title }}</title>

    <meta property="og:title" content="{{ $news->title }}">
    <meta property="og:description" content="{{ $snippet }}">
    @if($imageUrl)
    <meta property="og:image" content="{{ $imageUrl }}">
    <meta name="twitter:card" content="summary_large_image">
    @endif
    <meta property="og:type" content="article">
    <meta name="apple-itunes-app" content="app-id=1083351430">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Amiri&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Amiri', serif; }
        body { padding: 24px; max-width: 640px; margin: 0 auto; color: #2b2b2b; }
        .logo { display: block; width: 140px; margin: 0 auto 24px; }
        .image { width: 100%; border-radius: 8px; margin-bottom: 20px; }
        h1 { font-size: 24px; margin-bottom: 12px; }
        .details { font-size: 17px; line-height: 1.8; margin-bottom: 28px; }
    </style>
</head>
<body>
    <img class="logo" src="/landing/img/tayyar.jpeg" alt="FPM" onerror="this.style.display='none'">

    @if($imageUrl)
    <img class="image" src="{{ $imageUrl }}" alt="">
    @endif

    <h1>{{ $news->title }}</h1>
    <div class="details">{!! $news->details !!}</div>

    @include('partials.store-buttons')
</body>
</html>
