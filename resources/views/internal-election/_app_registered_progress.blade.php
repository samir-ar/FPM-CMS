{{-- Second block of the Election Progress pages (admin + standalone): same tiles,
     bar and district table, but against permitted voters who are registered and
     logged in to the app. Needs: $appTotal $appVoted $appRemaining $appTurnout $appDistrictRows --}}
<hr>
<h3 style="margin: 15px 0;">المسجلون والمتصلون بالتطبيق — Registered &amp; logged in to the app</h3>

<div class="row">
    <div class="col-md-3 col-sm-6">
        <div class="small-box bg-aqua">
            <div class="inner">
                <h3>{{ number_format($appTotal) }}</h3>
                <p>المسجلون والمتصلون بالتطبيق في جميع الاقضية المشاركة</p>
            </div>
            <div class="icon"><i class="fa fa-mobile"></i></div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="small-box bg-green">
            <div class="inner">
                <h3>{{ number_format($appVoted) }}</h3>
                <p>عدد المصوتين من المسجلين</p>
            </div>
            <div class="icon"><i class="fa fa-check-circle"></i></div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="small-box bg-yellow">
            <div class="inner">
                <h3>{{ number_format($appRemaining) }}</h3>
                <p>عدد المتبقين من المسجلين</p>
            </div>
            <div class="icon"><i class="fa fa-clock-o"></i></div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="small-box bg-red">
            <div class="inner">
                <h3>{{ $appTurnout }}%</h3>
                <p>النسبة المئوية من المسجلين</p>
            </div>
            <div class="icon"><i class="fa fa-pie-chart"></i></div>
        </div>
    </div>
</div>

<div style="display:flex; align-items:center; gap:10px;">
    <div class="progress election-progress-track" style="height: 25px; margin-bottom: 0; flex: 1;">
        <div class="progress-bar progress-bar-success" role="progressbar" style="width: {{ $appTurnout }}%;"></div>
    </div>
    <span style="white-space:nowrap; font-weight:bold; font-size:16px;">{{ $appTurnout }}%</span>
</div>

<div class="row" style="margin-top: 20px;">
    <div class="col-md-12">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">نسبة الاقتراع حسب القضاء — من المسجلين في التطبيق</h3>
                <p class="text-muted" style="margin: 5px 0 0;">مرتبة من الأعلى إلى الأدنى حسب نسبة الاقتراع.</p>
            </div>
            <div class="box-body">
                @if($appDistrictRows->isEmpty())
                    <div class="alert alert-warning" style="margin:0;">لا يوجد مسجلون ومتصلون بالتطبيق بعد.</div>
                @else
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>القضاء</th>
                                <th>المسجلون</th>
                                <th>المقترعون</th>
                                <th>لم يقترعوا</th>
                                <th>نسبة الاقتراع</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($appDistrictRows as $row)
                                <tr>
                                    <td>
                                        <a href="#" class="district-drilldown-link" data-base="app" data-district="{{ $row['district'] }}">{{ $row['district'] }}</a>
                                    </td>
                                    <td>{{ number_format($row['permitted']) }}</td>
                                    <td>{{ number_format($row['voted']) }}</td>
                                    <td>{{ number_format($row['remaining']) }}</td>
                                    <td style="min-width: 220px;">
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            <div class="progress election-progress-track" style="margin-bottom: 0; flex: 1;">
                                                <div class="progress-bar progress-bar-{{ $row['turnout'] < 40 ? 'danger' : ($row['turnout'] < 70 ? 'warning' : 'success') }}"
                                                     role="progressbar" style="width: {{ $row['turnout'] }}%;"></div>
                                            </div>
                                            <span style="white-space:nowrap; font-weight:bold;">{{ $row['turnout'] }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>
