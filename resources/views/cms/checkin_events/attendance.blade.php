@extends($layout)

@section('footer_resources')
<script>
    var attendanceTable;
    $(function () {
        attendanceTable = $('#attendanceTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('admin.checkin-events.attendance', $event->id) }}",
            order: [[0, 'desc']],
            columns: [
                { data: 'id', name: 'id' },
                { data: 'member_id', name: 'member_id' },
                { data: 'member_name', name: 'member_name', searchable: false, sortable: false },
                { data: 'checked_in_at', name: 'checked_in_at' },
                { data: 'scanned_by_name', name: 'scanned_by_name', searchable: false, sortable: false },
            ],
        });

        var searchTimeout;
        $('#memberSearchInput').on('input', function () {
            clearTimeout(searchTimeout);
            var q = $(this).val();
            $('#selectedMemberId').val('');
            if (q.length < 2) {
                $('#memberSearchResults').hide().empty();
                return;
            }
            searchTimeout = setTimeout(function () {
                $.get("{{ route('admin.checkin-events.attendance.search', $event->id) }}", { q: q }, function (data) {
                    var html = '';
                    if (data.length === 0) {
                        html = '<div class="list-group-item">لا توجد نتائج</div>';
                    }
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
            var id = $(this).data('id');
            if (!id) return;
            $('#selectedMemberId').val(id);
            $('#memberSearchInput').val($(this).text());
            $('#memberSearchResults').hide().empty();
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('#memberSearchWrapper').length) {
                $('#memberSearchResults').hide();
            }
        });

        $('#addAttendanceForm').on('submit', function (e) {
            e.preventDefault();
            var memberId = $('#selectedMemberId').val();
            if (!memberId) {
                alert('الرجاء اختيار عضو من القائمة أولاً');
                return;
            }
            $.post("{{ route('admin.checkin-events.attendance.store', $event->id) }}", {
                _token: '{{ csrf_token() }}',
                member_id: memberId,
            }).done(function (resp) {
                $('#memberSearchInput').val('');
                $('#selectedMemberId').val('');
                attendanceTable.ajax.reload();
                $('#inlineMessage').removeClass('alert-danger').addClass('alert-success')
                    .text(resp.message).show();
                setTimeout(function () { $('#inlineMessage').fadeOut(); }, 4000);
            }).fail(function (xhr) {
                $('#inlineMessage').removeClass('alert-success').addClass('alert-danger')
                    .text((xhr.responseJSON && xhr.responseJSON.message) || 'حدث خطأ').show();
            });
        });
    });
</script>
@endsection

@section('content')
<div class="box box-default">
    <div class="box-header with-border">
        <h3 class="box-title">الحضور — {{ $event->name }}</h3>
        <a href="{{ route('admin.checkin-events.index') }}" class="btn btn-default pull-right">عودة الى المناسبات</a>
    </div>
    <div class="box-body">
        <div id="inlineMessage" class="alert" style="display:none;"></div>

        <form id="addAttendanceForm" style="margin-bottom:20px;">
            <div style="display:flex; gap:8px; align-items:flex-start;">
                <div id="memberSearchWrapper" style="position:relative; width:420px;">
                    <input type="text" id="memberSearchInput" class="form-control" autocomplete="off"
                           placeholder="ابحث بالاسم أو رقم الهاتف أو رقم العضوية">
                    <div id="memberSearchResults" class="list-group"
                         style="display:none; position:absolute; z-index:1000; width:100%; max-height:260px; overflow-y:auto; margin-top:2px;"></div>
                </div>
                <input type="hidden" id="selectedMemberId">
                <button type="submit" class="btn btn-primary">إضافة</button>
            </div>
        </form>

        <table id="attendanceTable" class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>رقم العضوية</th>
                    <th>الاسم</th>
                    <th>وقت الحضور</th>
                    <th>سجّله</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
@endsection
