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

        <form method="POST" action="{{ route('admin.municipality-members.import-store') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label>ملف Excel (.xlsx)</label>
                <input type="file" name="file" class="form-control" accept=".xlsx,.xls" required>
                <p class="help-block">
                    الأعمدة المطلوبة: <strong>Col 1</strong> اسم البلدية,
                    <strong>Col 3</strong> اسم القرية,
                    <strong>Col 5</strong> اسم المرشح,
                    <strong>Col 7</strong> المنصب,
                    <strong>Col 8</strong> رقم الهاتف,
                    <strong>Col 10</strong> منتسب
                </p>
                <p class="text-danger"><strong>تنبيه:</strong> سيتم حذف جميع السجلات الحالية واستبدالها بالملف الجديد.</p>
            </div>
            <button type="submit" class="btn btn-success">استيراد</button>
            <a href="{{ route('admin.municipality-members.index') }}" class="btn btn-default">إلغاء</a>
        </form>
    </div>
</div>
@endsection
