<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $isPublic ? $news->title : 'تطبيق التيار الوطني الحر' }}</title>

    @if($isPublic)
    <meta property="og:title" content="{{ $news->title }}">
    <meta property="og:description" content="{{ $snippet }}">
    @if($imageUrl)
    <meta property="og:image" content="{{ $imageUrl }}">
    <meta name="twitter:card" content="summary_large_image">
    @endif
    @else
    <meta property="og:title" content="تطبيق التيار الوطني الحر">
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

    @if($isPublic)
        @if($imageUrl)
        <img class="image" src="{{ $imageUrl }}" alt="">
        @endif

        <h1>{{ $news->title }}</h1>
        <div class="details">{!! $news->details !!}</div>
    @else
        <p style="font-size:18px;text-align:center;margin:30px 0;line-height:1.8">هذا الخبر متاح لأعضاء التطبيق.<br>حمّل التطبيق وسجّل الدخول للاطلاع عليه.</p>
    @endif

    @include('partials.store-buttons')
</body>
</html>
