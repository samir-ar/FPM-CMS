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
            <a href="{{ route('admin.directory-members.template') }}" class="btn btn-default">
                <i class="fa fa-download"></i> تحميل نموذج Excel
            </a>
        </p>

        <form method="POST" action="{{ route('admin.directory-members.import-store') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label>ملف Excel (.xlsx)</label>
                <input type="file" name="file" class="form-control" accept=".xlsx,.xls" required>
                <p class="help-block">
                    الأعمدة المطلوبة: <strong>Col 1</strong> الفئة (يجب أن تطابق اسم فئة موجودة مسبقاً في Business Types),
                    <strong>Col 2</strong> الاسم,
                    <strong>Col 3</strong> الاختصاص,
                    <strong>Col 4</strong> الهاتف,
                    <strong>Col 5</strong> الترتيب (اختياري)
                </p>
                <p class="text-info"><strong>ملاحظة:</strong> الاستيراد إضافي فقط — لا يحذف أو يستبدل أي سجلات موجودة. الصفوف التي لا تطابق فئة موجودة أو لا تحتوي على اسم يتم تجاهلها.</p>
            </div>
            <button type="submit" class="btn btn-success">استيراد</button>
            <a href="{{ route('admin.directory-members.index') }}" class="btn btn-default">إلغاء</a>
        </form>
    </div>
</div>
@endsection
