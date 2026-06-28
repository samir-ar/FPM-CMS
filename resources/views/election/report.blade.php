<html>
    <head>
        <link rel="stylesheet" href="{{ asset('/cms/css/report.css') }}"/>
        <link rel="stylesheet" href="{{ asset('/landing/css/bootstrap.min.css') }}"/>
        <link rel="stylesheet" href="{{ asset('/cms/css/report.css') }}"/>
    </head>

    <body dir="rtl">
        <header>
            <img src="{{ asset('images/logo.png') }}" >
            <h1>  نتائج الإنتخابات الداخلية لعام  {{$election->created_at->year}} </h1>
        </header>

        <main>
            <div class="election-state-container">
                @foreach ($states as $state )
                    @php
                        $candidatesCount = $election->candidates()->where('election_state_id',$state->id)->count();
                        if($candidatesCount>5){
                            $candidatesCount=5;
                        }

                        if($candidatesCount){
                            $voters=  $election
                                        ->votes()
                                        ->whereHas('candidate',
                                        function($q) use($state){
                                            $q->where('election_state_id',$state->id);
                                        })->count()/$candidatesCount;
                        }else{
                            $voters = 0;
                        }
                    @endphp

                    <div class="headline-container">
                        <h2>
                            {{$state->name}}
                        </h2>
                    </div>


                    <div class="stats-container">
                        <div class='stats'>
                            <span class="stats-label">عدد الناخبين</span>
                            <span class="stats-data">
                                {{ $voters }}
                            </span>
                        </div>
<!--                        <div class='stats'>
                            <span class="stats-label">نسبة الإقتراع</span>
                            <span class="stats-data">{{round($voters*100/$state->voters_number)}}%</span>
                        </div>-->
                    </div>



                    <table class="table" dir="rtl">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">إسم المرشح</th>
                                <th scope="col">عدد الأصوات</th>
                                <th scope="col">عدد مرّات 1</th>
                                <th scope="col">عدد مرّات 2</th>
                                <th scope="col">عدد مرّات 3</th>
                                <th scope="col">عدد مرّات 4</th>
                                <th scope="col">عدد مرّات 5</th>
                                <th scope="col">المجموع</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $count = 1;
                            @endphp
                            @foreach ($election->votes()->selectRaw('`candidate_id`,SUM(`weight`) as `sum_weight`')->groupBy('candidate_id')->orderBy('sum_weight','desc')->whereHas('candidate',function($q)use($state){ $q->where('election_state_id',$state->id);})->get() as $rank)

                            @php
                                $candidate = $rank->candidate;
                            @endphp
                            <tr>
                                <th scope="row">{{$count}}</th>
                                <td>{{$candidate->name}}</td>
                                <td>{{$candidate->internalElectionVotes()->get()->count()}}</td>
                                <td>{{$candidate->internalElectionVotes()->where('rank',1)->get()->count()}}</td>
                                <td>{{$candidate->internalElectionVotes()->where('rank',2)->get()->count()}}</td>
                                <td>{{$candidate->internalElectionVotes()->where('rank',3)->get()->count()}}</td>
                                <td>{{$candidate->internalElectionVotes()->where('rank',4)->get()->count()}}</td>
                                <td>{{$candidate->internalElectionVotes()->where('rank',5)->get()->count()}}</td>
                                <td>{{$candidate->internalElectionVotes()->get()->sum('weight')}}</td>

                                @php
                                    $count++
                                @endphp
                            </tr>
                            @endforeach
                        </tbody>
                        </table>

                    @endforeach
                    </br>
                    </br>
                    </br>
            </div> <!--End of election-state-container-->
        </main>
    </body>
</html>
