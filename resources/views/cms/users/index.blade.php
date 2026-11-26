@extends('layouts.cms')

@section('header_resources')
    <style>
        .users-toolbar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; }
        .users-toolbar:before, .users-toolbar:after { display: none; }
        .users-toolbar .btn .fa { margin-right: 5px; }
        .users-filters .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 20px; margin-top: 0; }
        .users-filters label { font-size: 12px; font-weight: 600; color: #666; margin-bottom: 4px; }
        .users-filters .form-group { margin-bottom: 12px; }
        .users-filters .form-control, .users-filters .select2-container .select2-selection--single { height: 34px; border-radius: 3px; }
        .users-filters .select2-container--default .select2-selection--single { padding: 6px 4px; border-color: #d2d6de; }
        .users-filters .field-active .form-control,
        .users-filters .field-active .select2-selection { border-color: #3c8dbc !important; background-color: #f0f7fc; }
        .users-chips { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; min-height: 34px; }
        .users-chip { display: inline-flex; align-items: center; gap: 8px; background: #e8f2f9; border: 1px solid #c5ddee; color: #23587a; border-radius: 14px; padding: 3px 10px; font-size: 12px; }
        .users-chip a { color: #23587a; font-weight: 700; font-size: 14px; line-height: 1; }
        .users-chip a:hover { color: #c0392b; text-decoration: none; }
        .users-filters .users-tick-title { display: block; }
        .users-filters .users-tick { display: flex; align-items: center; gap: 8px; height: 34px; margin: 0; font-size: 14px; font-weight: 400; color: #333; cursor: pointer; }
        .users-filters .users-tick input { width: 17px; height: 17px; margin: 0; cursor: pointer; }
        .users-report { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
        .users-report .form-control { width: 170px; }
        #users-table th, #users-table td { white-space: nowrap; vertical-align: middle; }
        #users-table thead th { background: #f7f9fb; }
        #users-table tbody tr:hover td { background: #eef6fb; }
        #users-table .delete-link { display: inline-block; padding: 2px 8px; }
        #users-table .delete-link i { font-size: 16px; }
        .users-table-box .dataTables_filter input { width: 280px !important; }
        .users-table-box .dataTables_processing { z-index: 5; height: auto; padding: 14px; box-shadow: 0 2px 10px rgba(0,0,0,.15); font-weight: 600; }
    </style>
@endsection

@section('content')
    <div class="box box-primary">
        <div class="box-header with-border users-toolbar">
            <div class="btn-group" role="group" aria-label="Scan access">
                @foreach($scanTabs as $tab)
                    <a href="{{ $tab['url'] }}" class="btn {{ $tab['active'] ? 'btn-primary active' : 'btn-default' }}">{{ $tab['label'] }}</a>
                @endforeach
            </div>
            <div>
                <a href="{{ route('admin.users.export') }}" class="btn btn-success" title="Excel of all verified accounts (the filters do not apply)"><i class="fa fa-download"></i>Export Users</a>
                <a href="{{ route('admin.users.import.create') }}" class="btn btn-primary"><i class="fa fa-upload"></i>Import Users</a>
                <a href="{{ route('admin.users.login-limits') }}" class="btn btn-default"><i class="fa fa-lock"></i>Login Limits</a>
            </div>
        </div>

        <div class="box-body users-filters">
            <form method="GET" action="{{ route('admin.users.index') }}" id="users-filter-form">
                @if($scanAccess)
                    <input type="hidden" name="scan_access" value="{{ $scanAccess }}">
                @endif
                <div class="row">
                    @foreach($dropdowns as $d)
                        <div class="col-lg-2 col-md-4 col-sm-6">
                            <div class="form-group {{ $d['current'] !== '' ? 'field-active' : '' }}">
                                <label for="{{ $d['name'] }}">{{ $d['label'] }}</label>
                                <select name="{{ $d['name'] }}" id="{{ $d['name'] }}" class="form-control select2" style="width:100%">
                                    <option value="">{{ $d['all'] }}</option>
                                    @foreach($d['options'] as $value => $text)
                                        <option value="{{ $value }}" @selected($d['current'] === (string) $value)>{{ $text }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endforeach
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <div class="form-group {{ $filters['member_id'] !== '' ? 'field-active' : '' }}">
                            <label for="f_member">Member ID</label>
                            <div class="input-group">
                                <input type="text" name="f_member" id="f_member" value="{{ $filters['member_id'] }}" class="form-control" placeholder="Type and press Enter" inputmode="numeric">
                                <span class="input-group-btn">
                                    <button type="submit" class="btn btn-primary" title="Apply"><i class="fa fa-search"></i></button>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <div class="form-group {{ $filters['activity'] !== '' ? 'field-active' : '' }}">
                            <label for="f_activity">Activity</label>
                            <select name="f_activity" id="f_activity" class="form-control">
                                <option value="">All accounts</option>
                                @foreach($activityOptions as $value => $text)
                                    <option value="{{ $value }}" @selected($filters['activity'] === $value)>{{ $text }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-5 col-sm-6">
                        <div class="form-group">
                            <label class="users-tick-title">Since 24 September</label>
                            <label class="users-tick">
                                <input type="checkbox" name="f_new" value="1" @checked($filters['new'] === '1')>
                                Only accounts that opened the app since 24 Sept
                            </label>
                        </div>
                    </div>
                </div>
            </form>

            @if(count($chips))
                <div class="users-chips">
                    <span class="text-muted">Filtered by:</span>
                    @foreach($chips as $chip)
                        <span class="users-chip">{{ $chip['label'] }}: <bdi>{{ $chip['value'] }}</bdi>
                            <a href="{{ $chip['remove'] }}" title="Remove this filter" aria-label="Remove {{ $chip['label'] }} filter">&times;</a></span>
                    @endforeach
                    <a href="{{ $clearUrl }}" class="btn btn-link btn-sm">Clear all</a>
                </div>
            @endif
        </div>

        <div class="box-footer">
            <form method="GET" action="{{ route('admin.users.installation-report') }}" class="users-report">
                @foreach($reportParams as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
                <strong><i class="fa fa-bar-chart"></i> Installation Report</strong>
                <select name="period" class="form-control" aria-label="Report month">
                    @foreach($periods as $value => $text)
                        <option value="{{ $value }}" @selected($value === $currentPeriod)>{{ $text }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-warning"><i class="fa fa-file-excel-o"></i> Download Excel</button>
                <span class="text-muted">{{ count($reportParams) ? 'The filters above apply to the report.' : 'From 24 September to the end of the chosen month.' }}
                    @if($tableOnlyFiltered) Activity and the 24 Sept tick box change the table only. @endif</span>
            </form>
        </div>
    </div>

    <div class="box users-table-box">
        <div class="box-body">
            <div class="table-responsive" style="border:0">
                <table id="users-table" class="table table-bordered table-striped">
                    <thead>
                    <tr>
                        @foreach($headers as $header)
                            <th>{{ $header }}</th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('footer_resources')
    <script>
        $(function () {
            // the length / search / paging controls stay in place while only the table scrolls sideways
            $('.users-table-box .table-responsive').removeClass('table-responsive');

            $('#users-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {!! json_encode($ajaxUrl) !!},
                columns: {!! $columns !!},
                order: [[0, 'desc']],
                lengthMenu: [10, 25, 50, 100],
                pageLength: 25,
                dom: "<'row'<'col-sm-6'l><'col-sm-6'f>><'row'<'col-sm-12'<'users-scroll'tr>>><'row'<'col-sm-5'i><'col-sm-7'p>>",
                language: {
                    search: '',
                    searchPlaceholder: 'Search name, member ID or phone',
                    info: 'Showing _START_ to _END_ of _TOTAL_ accounts',
                    infoFiltered: '(searched in _MAX_)',
                    infoEmpty: 'No accounts',
                    zeroRecords: 'No account matches. Try removing a filter.',
                    processing: '<i class="fa fa-spinner fa-spin"></i> Loading…'
                }
            });
            $('.users-scroll').css({'overflow-x': 'auto'});

            // choosing a value applies it at once; a district / town / sect also clears the lists after it
            var order = ['f_district', 'f_town', 'f_sect', 'f_unit'];
            $('#users-filter-form select, #users-filter-form input[type=checkbox]').on('change', function () {
                var i = order.indexOf(this.name);
                if (i > -1) {
                    order.slice(i + 1).forEach(function (deeper) { $('#' + deeper).val(''); });
                }
                this.form.submit();
            });

            $('body').on('click', '.delete-link', function () {
                $('#deleteModal').find('form').attr('action', $(this).attr('id'));
            });
        });
    </script>
@endsection
