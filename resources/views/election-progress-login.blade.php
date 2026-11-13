<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex, nofollow">
    <title>تسجيل الدخول</title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; background: #f4f6f8; margin: 0; padding: 40px 20px; }
        .box { background: #fff; border-radius: 6px; padding: 24px; max-width: 360px; margin: 0 auto; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        h1 { font-size: 18px; margin: 0 0 18px; text-align: center; }
        label { display: block; margin-bottom: 6px; font-size: 13px; color: #555; }
        input { width: 100%; box-sizing: border-box; padding: 9px 10px; margin-bottom: 14px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        button { width: 100%; padding: 10px; background: #2563eb; color: #fff; border: none; border-radius: 4px; font-size: 14px; cursor: pointer; }
        .error { background: #fde8e8; color: #c0392b; padding: 10px; border-radius: 4px; margin-bottom: 14px; font-size: 13px; }
    </style>
</head>
<body>
    <div class="box">
        <h1>متابعة نسبة الاقتراع</h1>

        @if($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('election-progress.login') }}">
            @csrf
            <label for="username">اسم المستخدم</label>
            <input type="text" name="username" id="username" autocomplete="off" required>
            <label for="password">كلمة المرور</label>
            <input type="password" name="password" id="password" required>
            <button type="submit">دخول</button>
        </form>
    </div>
</body>
</html>
