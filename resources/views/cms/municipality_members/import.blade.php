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
            <a href="{{ route('admin.municipality-members.template') }}" class="btn btn-default">
                <i class="fa fa-download"></i> تحميل نموذج Excel
            </a>
        </p>

        <form method="POST" action="{{ route('admin.municipality-members.import-store') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label>ملف Excel (.xlsx)</label>
                <input type="file" name="file" class="form-control" accept=".xlsx,.xls" required>
                <p class="help-block">
                    كل صف = عضو واحد، ويحتوي كل صف على قيمه الخاصة لكل عمود (لا داعي لترك خانات فارغة اعتماداً على الصف الذي قبله). الأعمدة بالترتيب:
                    <strong>القضاء</strong>,
                    <strong>البلدية</strong>,
                    <strong>القرية (اختياري)</strong>,
                    <strong>اسم العضو</strong>,
                    <strong>عدد الأصوات</strong>,
                    <strong>المنصب</strong>,
                    <strong>رقم الهاتف (اختياري)</strong>,
                    <strong>منتسب (اختياري)</strong>
                </p>
                <p class="text-info"><strong>ملاحظة:</strong> يمكن رفع كل الأقضية في ملف واحد. عند الاستيراد، يتم تلقائياً حذف السجلات القديمة الخاصة <u>بنفس القضاء</u> الموجود في الملف واستبدالها بالسجلات الجديدة — أقضية أخرى غير موجودة في الملف لا تتأثر إطلاقاً. لتحديث قضاء (مثل تحديث بيانات المتن)، يكفي رفع الملف المحدّث وسيتم استبدال بياناته القديمة تلقائياً.</p>
            </div>
            <button type="submit" class="btn btn-success">استيراد</button>
            <a href="{{ route('admin.municipality-members.index') }}" class="btn btn-default">إلغاء</a>
        </form>
    </div>
</div>
@endsection
