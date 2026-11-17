@extends($layout)

@section('footer_resources')
<script>
    $(function () {
        var csrf = '{{ csrf_token() }}';

        function flash(ok, text) {
            $('#inlineMessage').removeClass('alert-success alert-danger').addClass(ok ? 'alert-success' : 'alert-danger').text(text).show();
        }

        // ---- search + add one ----
        var searchTimeout;
        $('#memberSearchInput').on('input', function () {
            clearTimeout(searchTimeout);
            var q = $(this).val();
            $('#selectedMemberId').val('');
            if (q.length < 2) { $('#memberSearchResults').hide().empty(); return; }
            searchTimeout = setTimeout(function () {
                $.get("{{ route('admin.council-roster.search') }}", { q: q }, function (data) {
                    var html = data.length ? '' : '<div class="list-group-item">لا توجد نتائج</div>';
                    data.forEach(function (m) {
                        html += '<a href="#" class="list-group-item member-result" data-id="' + m.member_id + '">' +
                            (m.name || '') + ' — ' + (m.phone || '') + ' — #' + m.member_id + '</a>';
                    });
                    $('#memberSearchResults').html(html).show();
                });
            }, 300);
        });
        $(document).on('click', '.member-result', function (e) {
            e.preventDefault();
            $('#selectedMemberId').val($(this).data('id'));
            $('#memberSearchInput').val($(this).text());
            $('#memberSearchResults').hide().empty();
        });

        $('#addMemberForm').on('submit', function (e) {
            e.preventDefault();
            var id = $('#selectedMemberId').val();
            if (!id) { alert('الرجاء اختيار عضو من القائمة أولاً'); return; }
            $.post("{{ route('admin.council-roster.store') }}", {
                _token: csrf, member_id: id,
                weight: $('#newWeight').val(), role: $('#newRole').val(),
                sector: $('#newSector').val(), district: $('#newDistrict').val()
            }).done(function (r) { location.reload(); })
              .fail(function (xhr) { flash(false, (xhr.responseJSON && xhr.responseJSON.message) || 'حدث خطأ'); });
        });

        // ---- save a row / remove a row ----
        $(document).on('click', '.save-row', function () {
            var tr = $(this).closest('tr');
            $.ajax({
                url: tr.data('url'), type: 'PUT',
                data: {
                    _token: csrf, weight: tr.find('.f-weight').val(), role: tr.find('.f-role').val(),
                    sector: tr.find('.f-sector').val(), district: tr.find('.f-district').val(),
                    is_active: tr.find('.f-active').is(':checked') ? 1 : 0
                }
            }).done(function (r) { flash(true, r.message); setTimeout(function () { location.reload(); }, 600); })
              .fail(function (xhr) { flash(false, (xhr.responseJSON && xhr.responseJSON.message) || 'حدث خطأ'); });
        });
        $(document).on('click', '.remove-row', function () {
            var tr = $(this).closest('tr');
            if (!confirm('إزالة ' + tr.data('name') + ' من لائحة المجلس؟')) return;
            $.ajax({ url: tr.data('url'), type: 'DELETE', data: { _token: csrf } })
              .done(function () { location.reload(); })
              .fail(function (xhr) { flash(false, (xhr.responseJSON && xhr.responseJSON.message) || 'حدث خطأ'); });
        });
    });
</script>
@endsection

@section('content')
<div class="box box-default">
    <div class="box-header with-border">
        <h3 class="box-title">لائحة أعضاء المجلس الوطني</h3>
        <a href="{{ route('admin.council-meetings.index') }}" class="btn btn-default pull-right" style="margin-right:4px;">الاجتماعات</a>
        <a href="{{ route('admin.council-roster.export') }}" class="btn btn-success pull-right" style="margin-right:4px;">تصدير الى Excel</a>
    </div>
    <div class="box-body">
        <div id="inlineMessage" class="alert" style="display:none;"></div>

        <div class="row" style="margin-bottom:15px;">
            <div class="col-md-3"><strong>عدد الأعضاء:</strong> {{ $memberCount }}</div>
            <div class="col-md-3"><strong>مجموع الأوزان:</strong> {{ $totalWeight }}</div>
            <div class="col-md-3"><strong>النصف + 1:</strong> {{ $majority }}</div>
            <div class="col-md-3"><strong>الثلثان:</strong> {{ $twoThirds }}</div>
        </div>
        <p class="text-muted">
            هذه الأرقام تُقرأ مباشرة من اللائحة أدناه (الأعضاء الفعّالون وأوزانهم). الاجتماع الجديد يثبّت هذه الأرقام عند إنشائه،
            فتأكد من اكتمال اللائحة قبل إنشاء الاجتماع. إضافة عضو لاحقاً لا تغيّر نصاب اجتماع قائم، ويمكن تحديثه بزر «تحديث النصاب» في صفحة الاجتماع.
        </p>

        <h4>إضافة عضو</h4>
        <form id="addMemberForm" style="margin-bottom:20px;">
            <div style="display:flex; gap:8px; align-items:flex-start; flex-wrap:wrap;">
                <div id="memberSearchWrapper" style="position:relative; width:380px;">
                    <input type="text" id="memberSearchInput" class="form-control" autocomplete="off"
                           placeholder="ابحث بالاسم أو رقم الهاتف أو رقم العضوية">
                    <div id="memberSearchResults" class="list-group"
                         style="display:none; position:absolute; z-index:1000; width:100%; max-height:260px; overflow-y:auto; margin-top:2px;"></div>
                </div>
                <input type="hidden" id="selectedMemberId">
                <input type="number" id="newWeight" class="form-control" style="width:90px;" value="1" min="0" placeholder="الوزن">
                <input type="text" id="newRole" class="form-control" style="width:160px;" placeholder="الصفة">
                <input type="text" id="newSector" class="form-control" style="width:140px;" placeholder="القطاع">
                <input type="text" id="newDistrict" class="form-control" style="width:140px;" placeholder="القضاء">
                <button type="submit" class="btn btn-primary">إضافة</button>
            </div>
        </form>

        <h4>استيراد من Excel (إضافة / تحديث فقط، لا يحذف أحداً)</h4>
        <form method="POST" action="{{ route('admin.council-roster.import') }}" enctype="multipart/form-data" style="margin-bottom:25px;">
            @csrf
            <input type="file" name="excel" accept=".xlsx,.xls,.csv" style="display:inline-block;">
            <button type="submit" class="btn btn-default">استيراد</button>
            <a href="{{ route('admin.council-roster.template') }}">تحميل نموذج Excel</a>
            <small class="text-muted">الأعمدة: رقم العضوية | الوزن | الصفة | القطاع | القضاء</small>
        </form>

        <table class="table table-bordered table-striped" dir="rtl">
            <thead>
                <tr>
                    <th>#</th>
                    <th>رقم العضوية</th>
                    <th>الاسم</th>
                    <th>الهاتف</th>
                    <th>الصفة</th>
                    <th>القطاع</th>
                    <th>القضاء</th>
                    <th>الوزن</th>
                    <th>فعّال</th>
                    <th>التطبيق</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($members as $i => $m)
                @php($d = $details[$m->member_id] ?? null)
                <tr data-url="{{ route('admin.council-roster.update', $m->id) }}" data-name="{{ $d->UserFullName ?? $m->member_id }}"
                    @if(!$m->is_active) style="opacity:.5" @endif>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $m->member_id }}</td>
                    <td>{{ $d->UserFullName ?? '— غير موجود في لائحة الأعضاء —' }}</td>
                    <td>{{ $d->MobileNumber ?? '' }}</td>
                    <td><input type="text" class="form-control f-role" value="{{ $m->role }}"></td>
                    <td><input type="text" class="form-control f-sector" value="{{ $m->sector }}"></td>
                    <td><input type="text" class="form-control f-district" value="{{ $m->district }}"></td>
                    <td><input type="number" class="form-control f-weight" style="width:80px;" min="0" value="{{ $m->weight }}"></td>
                    <td><input type="checkbox" class="f-active" @if($m->is_active) checked @endif></td>
                    <td>@if(isset($loggedIn[$m->member_id])) <span class="text-success">✔ مسجّل</span> @else <span class="text-danger">✖ لم يسجّل دخولاً</span> @endif</td>
                    <td style="white-space:nowrap;">
                        <button type="button" class="btn btn-xs btn-primary save-row">حفظ</button>
                        <button type="button" class="btn btn-xs btn-danger remove-row">إزالة</button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
