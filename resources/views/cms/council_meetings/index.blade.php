@extends($layout)

@section('content')
<div class="box box-default">
    <div class="box-header with-border">
        <h3 class="box-title">اجتماعات المجلس الوطني</h3>
        <a href="{{ route('admin.council-meetings.create') }}" class="btn btn-primary pull-right" style="margin-right:4px;">اجتماع جديد</a>
        <a href="{{ route('admin.council-roster.index') }}" class="btn btn-default pull-right" style="margin-right:4px;">لائحة الأعضاء</a>
    </div>
    <div class="box-body">
        <table class="table table-bordered table-striped" dir="rtl">
            <thead>
                <tr>
                    <th>#</th>
                    <th>الاجتماع</th>
                    <th>التاريخ</th>
                    <th>الحضور</th>
                    <th>وزن الحاضرين / المجموع</th>
                    <th>النصف + 1</th>
                    <th>الثلثان</th>
                    <th>الحالة</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($meetings as $row)
                @php($m = $row['meeting'])
                @php($s = $row['stats'])
                <tr>
                    <td>{{ $m->id }}</td>
                    <td>{{ $m->title }}</td>
                    <td>{{ optional($m->meeting_date)->format('Y-m-d') }}</td>
                    <td>{{ $s['present_count'] }} / {{ $s['member_count'] }}</td>
                    <td>{{ $s['present_weight'] }} / {{ $s['total_weight'] }}</td>
                    <td><span class="{{ $s['majority_reached'] ? 'text-success' : 'text-danger' }}">{{ $s['majority_reached'] ? '✔' : '✖' }} {{ $s['quorum_majority'] }}</span></td>
                    <td><span class="{{ $s['two_thirds_reached'] ? 'text-success' : 'text-danger' }}">{{ $s['two_thirds_reached'] ? '✔' : '✖' }} {{ $s['quorum_two_thirds'] }}</span></td>
                    <td>{!! $m->is_active ? '<span class="text-success">مفتوح</span>' : '<span class="text-warning">مغلق</span>' !!}</td>
                    <td><a class="btn btn-xs btn-primary" href="{{ route('admin.council-meetings.show', $m->id) }}">الحضور</a></td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center">لا توجد اجتماعات بعد.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
