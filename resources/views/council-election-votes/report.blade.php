<html>
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="{{ asset('/cms/css/report.css') }}"/>
    <style>
        .bg-orange{
            background: var(--orange);
        }
    </style>
</head>

<body dir="rtl">
<header>
    <img src="{{ asset('landing/img/fpm-logo.svg') }}" >
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
                <span>{{round($votersCount *100 /$electorsCount,3)}}</span>
            </div>
            

            <div class="col-3">
                <span> عدد الأسئلة:</span>
                <span>{{$poll->questions()->count()}}</span>
            </div>
        </div>

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
          </div>
        <br>

            <table class="table" dir="rtl">
                <thead>
                    <tr>
                        @foreach($question->answers as $answer)
                            <th scope="col">{{$answer->answer}}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        @foreach($question->answers as $answer)
                            <td>
                                {{$votes->where('answer_id',$answer->id)->sum('weight')}}
                            </td>
                        @endforeach
                    </tr>
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
