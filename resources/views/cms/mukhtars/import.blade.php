@extends($layout)
@section('content')
<div class="box box-default">
    <div class="box-header with-border">
        <h3 class="box-title">{{ $pageTitle }}</h3>
    </div>
    <div class="box-body">
        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif

        <p>
            <a href="{{ route('admin.mukhtars.template') }}" class="btn btn-default">
                <i class="fa fa-download"></i> تحميل نموذج Excel
            </a>
        </p>

        <form method="POST" action="{{ route('admin.mukhtars.import-store') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label>ملف Excel (.xlsx)</label>
                <input type="file" name="file" class="form-control" accept=".xlsx,.xls" required>
                <p class="help-block">
                    الأعمدة: <strong>Col 1</strong> البلدة/القرية,
                    <strong>Col 2</strong> اسم الحي,
                    <strong>Col 3</strong> اسم المرشح,
                    <strong>Col 5</strong> مرشح عن (مختار/عضو اختياري),
                    <strong>Col 6</strong> الجوال,
                    <strong>Col 8</strong> رقم الانتساب (منتسب)
                </p>
                <p class="text-info"><strong>ملاحظة:</strong> عند الاستيراد، يتم تلقائياً حذف السجلات القديمة الخاصة <u>بنفس القضاء</u> الموجود في الملف واستبدالها بالسجلات الجديدة — أقضية أخرى غير موجودة في الملف لا تتأثر إطلاقاً. لتحديث قضاء (مثل تحديث بيانات المتن)، يكفي رفع الملف المحدّث لنفس القضاء وسيتم استبدال بياناته القديمة تلقائياً.</p>
            </div>
            <button type="submit" class="btn btn-success">استيراد</button>
            <a href="{{ route('admin.mukhtars.index') }}" class="btn btn-default">إلغاء</a>
        </form>
    </div>
</div>
@endsection
