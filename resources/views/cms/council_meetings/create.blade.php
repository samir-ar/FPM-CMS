@extends($layout)

@section('content')
<div class="box box-default">
    <div class="box-header with-border">
        <h3 class="box-title">اجتماع جديد</h3>
    </div>
    <div class="box-body">
        <form method="POST" action="{{ route('admin.council-meetings.store') }}" style="max-width:560px;">
            @csrf
            <div class="form-group">
                <label>عنوان الاجتماع</label>
                <input type="text" name="title" class="form-control" required value="{{ old('title') }}" placeholder="مثال: اجتماع المجلس الوطني 29 تشرين الثاني 2026">
            </div>
            <div class="form-group">
                <label>التاريخ</label>
                <input type="date" name="meeting_date" class="form-control" value="{{ old('meeting_date') }}">
            </div>
            <div class="alert alert-info">
                سيتم تثبيت النصاب عند الإنشاء على لائحة المجلس الحالية:
                <strong>{{ $memberCount }}</strong> عضواً، مجموع الأوزان <strong>{{ $totalWeight }}</strong>،
                النصف + 1 = <strong>{{ $majority }}</strong>، الثلثان = <strong>{{ $twoThirds }}</strong>.
                <br>تأكد من اكتمال اللائحة قبل الإنشاء. يمكن لاحقاً تحديث النصاب بزر "تحديث النصاب".
            </div>
            <button type="submit" class="btn btn-primary">إنشاء الاجتماع</button>
            <a href="{{ route('admin.council-meetings.index') }}" class="btn btn-default">إلغاء</a>
        </form>
    </div>
</div>
@endsection
