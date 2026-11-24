<html>
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="{{ url('cms/css/report.css') }}"/>
    <style>
        .bg-orange{
            background: var(--orange);
        }
    </style>
</head>

<body dir="rtl">
<header>
    <img src="{{ url('landing/img/fpm-logo.svg') }}" >
    <h1>  نتائج التصويت للمجلس الوطني لعام  {{$poll->created_at->year}} </h1>
    <h6>{{$title}}</h6>
</header>

<main>
    <div class="container">
        <div class="bg-orange">
            <h3 class="text-white p-1 px-3 fs-4 mt-4">معلومات عامة:</h3>
        </div>
        <div class="row px-2">
            <div class="col-3">
                <span>عدد الناخبين:</span>
                <span>{{$electorsCount}}</span>
            </div>

            <div class="col-3">
                <span>عدد المشاركين:</span>
                <span>{{$votersCount}}</span>
            </div>

            <div class="col-3">
                <span>نسبة التصويت:</span>
                <span>{{ $electorsCount ? round($votersCount *100 /$electorsCount,3) : 0 }}</span>
            </div>
            

            <div class="col-3">
                <span> عدد الأسئلة:</span>
                <span>{{$poll->questions()->count()}}</span>
            </div>
        </div>

        <div class="row px-2 mt-2">
            <div class="col-3">
                <span>مجموع أوزان الناخبين:</span>
                <span>{{$totalWeight}}</span>
            </div>
            <div class="col-3">
                <span>الوزن المصوِّت:</span>
                <span>{{$votedWeight}}</span>
            </div>
            @if($meetingStats)
            <div class="col-6">
                <span>الاجتماع:</span>
                <span>{{$poll->meeting->title}}</span>
                <span>— الحضور: {{$meetingStats['present_count']}} / {{$meetingStats['member_count']}}
                    (وزن {{$meetingStats['present_weight']}} من {{$meetingStats['total_weight']}})</span>
            </div>
            @endif
        </div>
        <form method="GET" class="px-2 mt-3">
            <label>إبراز أول</label>
            <input type="number" name="top" min="0" value="{{$top ?: ''}}" style="width:70px;" placeholder="مثلاً 6">
            <button type="submit">عرض</button>
            <small>(لانتخاب عدة مقاعد: يُظهر الفائزين حسب الوزن)</small>
        </form>

        <br>
        <br>
        <br>
        {{-- Information about each questions --}}
        <div class="bg-orange">
            <h3 class="text-white p-1 px-3 fs-4 mt-4">معلومات عن توزيع الأصوات:</h3>
        </div>

      @foreach($poll->questions as $question)
          <div class="px-2">
              <span>السؤال:</span>
              <span>{{$question->question}}</span>
              <span>— مجموع الأوزان المصوّتة: {{$ranking[$question->id]['cast_weight']}}</span>
          </div>
        <br>

            @if($ranking[$question->id]['tie_at_cutoff'])
                <div class="alert alert-warning mx-2">تعادل في الوزن عند حد الفوز (المركز {{$top}} والذي يليه)، يلزم قرار.</div>
            @endif

            <table class="table" dir="rtl">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">الخيار / المرشح</th>
                        <th scope="col">الوزن</th>
                        <th scope="col">عدد الأصوات</th>
                        <th scope="col">النسبة من الوزن</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($ranking[$question->id]['rows'] as $row)
                        <tr @if($top && $row['rank'] <= $top) style="background:#e6f4ea; font-weight:bold;" @endif>
                            <td>{{$row['rank']}}</td>
                            <td>{{$row['answer']}}</td>
                            <td>{{$row['weight']}}</td>
                            <td>{{$row['votes']}}</td>
                            <td>{{$row['pct']}}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    </div>

    <br>
    <br>

    <div class="container mt-3">
        <div class="bg-orange">
            <h3 class="text-white p-1 px-3 fs-4 mt-4">المشاركين:</h3>
        </div>

        <table class="table" dir="rtl">
                    <thead>
                            <tr>
                        <th>الإسم</th>
                        <th>الرقم</th>
                       {{-- @foreach($poll->questions as $question)
                                 <th scope="col">{{$question->question}}</th>
                        @endforeach--}}
                            </tr>
                    </thead>

                    <tbody>

                    @foreach($votes->groupBy('user_id') as $vote)
                        <tr>

                            <td>{{$vote->first()->user->name}}</td>
                            <td>{{$vote->first()->user->member_id}}</td>
                            {{--@foreach($vote as $answer)
                                    <td>
                                        {{$answer->answer->answer}}
                                    </td>
                            @endforeach--}}
                        </tr>
                    @endforeach

                    </tbody>
            </table>
    </div>
</main>
</body>
</html>
