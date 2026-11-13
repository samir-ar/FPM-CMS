<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex, nofollow">
    <title>اختر الانتخابات</title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; background: #f4f6f8; margin: 0; padding: 40px 20px; }
        .box { background: #fff; border-radius: 6px; padding: 24px; max-width: 420px; margin: 0 auto; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        h1 { font-size: 18px; margin: 0 0 18px; }
        a.item { display: block; padding: 12px; margin-bottom: 8px; background: #f0f4ff; border-radius: 4px; color: #2563eb; text-decoration: none; font-size: 14px; }
        a.item:hover { background: #e2e9fc; }
        .empty { color: #888; font-size: 14px; }
        form.logout { margin-top: 20px; text-align: center; }
        form.logout button { background: none; border: none; color: #888; font-size: 12px; cursor: pointer; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="box">
        <h1>الانتخابات الفعالة حالياً</h1>

        @if($elections->isEmpty())
            <div class="empty">لا يوجد انتخابات فعالة حالياً.</div>
        @else
            @foreach($elections as $election)
                <a class="item" href="{{ route('election-progress.show', $election->id) }}">{{ $election->title }}</a>
            @endforeach
        @endif

        <form class="logout" method="POST" action="{{ route('election-progress.logout') }}">
            @csrf
            <button type="submit">تسجيل الخروج</button>
        </form>
    </div>
</body>
</html>
