@extends($layout)

@section('footer_resources')
<script>
    $(function () {
        $(document).on('click', '.add-to-roster', function () {
            var tr = $(this).closest('tr');
            $.post("{{ route('admin.council-roster.store') }}", {
                _token: '{{ csrf_token() }}',
                member_id: tr.data('id'),
                weight: tr.find('.roster-weight').val(),
                role: tr.find('.roster-role').val(),
                sector: tr.data('sector')
            }).done(function (r) { alert(r.message); location.reload(); })
              .fail(function (xhr) { alert((xhr.responseJSON && xhr.responseJSON.message) || 'حدث خطأ'); });
        });
    });
</script>
@endsection

@section('content')
<div class="box box-default">
    <div class="box-header with-border">
        <h3 class="box-title">أعضاء خاصون (بدون رقم عضوية رسمي)</h3>
    </div>
    <div class="box-body">
        <p class="text-muted">
            لشخص يجب أن يسجّل الدخول ويُحتسب (مثلاً عضو في المجلس الوطني) وليس لديه رقم عضوية رسمي من TWH.
            يُعطى رقماً من نطاق محجوز لا يتعارض مع الأرقام الحقيقية (13 رقماً يبدأ بـ 99)، ويُنشأ له سجل في لائحة الأعضاء
            ليعمل الدخول والحضور والتصويت كأي عضو. يُحتفظ بنسخة من بياناته هنا ويمكن استعادتها إن حذفتها مزامنة TWH.
        </p>

        <h4>إضافة عضو خاص</h4>
        <form method="POST" action="{{ route('admin.special-members.store') }}" enctype="multipart/form-data" style="max-width:640px; margin-bottom:30px;">
            @csrf
            <div class="form-group">
                <label>الاسم الكامل *</label>
                <input type="text" name="full_name" class="form-control" required value="{{ old('full_name') }}">
            </div>
            <div class="form-group">
                <label>رقم الهاتف مع رمز الدولة * (هو رقم تسجيل الدخول)</label>
                <input type="text" name="phone" class="form-control" required dir="ltr" value="{{ old('phone') }}" placeholder="+1 5122990023">
            </div>
            <div class="row">
                <div class="col-md-6 form-group">
                    <label>القطاع / الصفة</label>
                    <input type="text" name="sector" class="form-control" value="{{ old('sector') }}" placeholder="مدن الولايات المتحدة الأميركية">
                </div>
                <div class="col-md-6 form-group">
                    <label>المنطقة / القضاء</label>
                    <input type="text" name="district" class="form-control" value="{{ old('district') }}">
                </div>
            </div>
            <div class="form-group">
                <label>البريد الإلكتروني (اختياري)</label>
                <input type="email" name="email" class="form-control" dir="ltr" value="{{ old('email') }}">
            </div>
            <div class="form-group">
                <label>الصورة (تُصغَّر تلقائياً)</label>
                <input type="file" name="photo" accept="image/*">
            </div>
            <div class="form-group">
                <label>ملاحظة</label>
                <textarea name="note" class="form-control" rows="2">{{ old('note') }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary">إضافة</button>
        </form>

        <h4>الأعضاء الخاصون ({{ $members->count() }})</h4>
        <table class="table table-bordered table-striped" dir="rtl">
            <thead>
                <tr><th>رقم العضوية</th><th>الاسم</th><th>الهاتف</th><th>القطاع</th><th>في لائحة الأعضاء</th><th>سجّل دخوله</th><th>لائحة المجلس</th><th></th></tr>
            </thead>
            <tbody>
            @forelse($members as $m)
                @php($row = $inFpm[(string) $m->member_id] ?? null)
                <tr data-id="{{ $m->member_id }}" data-sector="{{ $m->sector }}">
                    <td>{{ $m->member_id }}</td>
                    <td>{{ $m->full_name }}</td>
                    <td dir="ltr">+{{ $m->phone }}</td>
                    <td>{{ $m->sector }}</td>
                    <td>
                        @if($row)
                            {!! $row->member_status ? '<span class="text-success">✔ فعّال</span>' : '<span class="text-warning">معطّل</span>' !!}
                        @else
                            <span class="text-danger">✖ مفقود</span>
                        @endif
                    </td>
                    <td>@if(isset($loggedIn[(string) $m->member_id])) <span class="text-success">✔</span> @else <span class="text-muted">لا</span> @endif</td>
                    <td>
                        @if(isset($inRoster[(string) $m->member_id]))
                            <span class="text-success">✔ مضاف</span>
                        @else
                            <input type="number" class="roster-weight" style="width:60px;" min="0" value="1" title="الوزن">
                            <input type="text" class="roster-role" style="width:110px;" placeholder="الصفة" value="{{ $m->sector }}">
                            <button type="button" class="btn btn-xs btn-primary add-to-roster">إضافة للمجلس</button>
                        @endif
                    </td>
                    <td style="white-space:nowrap;">
                        @if(!$row)
                            <a class="btn btn-xs btn-warning" href="{{ route('admin.special-members.restore', $m->id) }}" onclick="return confirm('إعادة السجل الى لائحة الأعضاء؟')">استعادة</a>
                        @else
                            <a class="btn btn-xs btn-default" href="{{ route('admin.special-members.toggle-status', $m->id) }}">{{ $row->member_status ? 'تعطيل' : 'تفعيل' }}</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center">لا يوجد أعضاء خاصون بعد.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
