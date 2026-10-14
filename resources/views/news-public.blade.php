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
        .open-app {
            display: block;
            background-color: #f68f1f;
            color: #fff;
            text-align: center;
            padding: 14px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            text-decoration: none;
            margin-bottom: 16px;
        }
        .stores { display: flex; gap: 12px; justify-content: center; }
        .stores a { display: block; }
        .stores img { height: 44px; }
    </style>
</head>
<body>
    <img class="logo" src="/landing/img/tayyar.jpeg" alt="FPM" onerror="this.style.display='none'">

    @if($imageUrl)
    <img class="image" src="{{ $imageUrl }}" alt="">
    @endif

    <h1>{{ $news->title }}</h1>
    <div class="details">{!! $news->details !!}</div>

    <a class="open-app" href="https://fpmmobile.com/news/{{ $news->id }}">افتح في تطبيق التيار الوطني الحر</a>
    <div class="stores">
        <a href="https://play.google.com/store/apps/details?id=com.microbits.fpm">Google Play</a>
        <a href="https://apps.apple.com/app/id1083351430">App Store</a>
    </div>
</body>
</html>
