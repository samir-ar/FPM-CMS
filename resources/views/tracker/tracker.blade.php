<html dir="rtl">

<head>
    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

    <!-- jQuery library -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>

    <!-- Popper JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>

    <!-- Latest compiled JavaScript -->
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <style>
        #accordion{
            margin-bottom:30px !important;
        }

        
        .card-header{
            font-weight: 700;
            font-size: 1.2rem;
        }
        
        .card-body{
            font-size: 1.2rem;
        }

        html{
            font-size:20px;
        }

        body{
            background-color:#f6f5f0;
        }

        * {
            box-sizing: border-box;
        }

        header{
            padding:50px 0 0 0;
        }
        header h1{
            font-weight: 900;
            text-align: center;
        }

        main{
            padding:40px;
        }

        .box a{
            color: black;
        }


        .box{
            margin-bottom:30px;

            background: white;
            border-radius: 30px;
            text-align: initial;
            padding: 30px;
            box-shadow: 0 0 3px #b9b9b9;
            }

        .box h2{
            font-size:2rem;
            font-weight: 900;
            margin-bottom: 40px;
        }

        ul{
            list-style: none;
        }
        ul li{
            display: flex;
        }

        ul span:first-child {
            font-weight:900;
            margin-inline-end:10px;
            flex:1;
            
        }

        ul span:nth-child(2) {
            font-size: 15px;
            color: #585858;
        }
        
        
        ul li{
            padding: 10px;
            border-bottom: 1px solid #bdbdbd;
        }

        ul li span{
            direction: initial;
        }


    </style>

    <title>Tracker</title>
</head>

<body>

    <header>
        <h1> منصة تتبع طلبات الترشيح</h1>
    </header>
    <main>



        <div class="box">
            <h2>طلبات الترشح الخاصة بي:</h2>
            

            <!--District coordinator-->
            @if ($myDistrictBodyCoordinatorAppliction->count() > 0)

                @php
                    $dbc = $myDistrictBodyCoordinatorAppliction->sortByDesc('id')->first();
                @endphp

                <div id="accordion">
                    <div class="card">
                        <a class="card-link" data-toggle="collapse" href="#collapseOne">
                        <div class="card-header">
                                منسق قضاء

                                @if ($dbc->state == 'WAITING')
                                    <span class="badge badge-info">قيد الإنتظار</span>
                                @endif

                                @if ($dbc->state == 'APPROVED')
                                    <span class="badge badge-success">مقبول</span>
                                @endif

                                @if ($dbc->state == 'DENIED')
                                    <span class="badge badge-danger">مرفوض</span>
                                @endif

                            </div>
                        </a>

                        @php
                            $labels = [' SG لاقتراح VP استلام', 'SG طلب تحضير التعميم من ', 'وضع التواقيع + SG استلام التعميم من', 'VP إلى SG إرسال التعميم من ', 'VP إلى SG إرسال نهائي من', 'على اصدار التعميم VP موافقى ال', 'وتعميم GB من SG إستلام'];
                        @endphp

                        <div id="collapseOne" class="collapse " data-parent="#accordion">
                            <div class="card-body">
                                <ul>
                                    @php
                                        $counter = 1;
                                    @endphp

                                    @foreach ($labels as $label)
                                        <li>
                                            <span>
                                                {{ $label }}
                                            </span>
                                            <span>
                                                {{ ($date = $dbc['phase_' . $counter]) ? $date : 'قيد الانتظار' }}
                                            </span>
                                        </li>
                                        @php
                                            $counter++;
                                        @endphp
                                    @endforeach

                                    <li>
                                        <span>
                                            رقم التعميم
                                        </span>
                                        <span>
                                            {{ ($date = $dbc->popularization_no) ? $date : 'قيد الانتظار' }}
                                        </span>
                                    </li>

                                </ul>
                            </div>
                        </div>


                    </div>

                </div>
            @endif


            <!--District Body Memeber-->
            @if ($myDistrictBodyMembersApplication->count() > 0)

                @php
                    $dbc = $myDistrictBodyMembersApplication->sortByDesc('id')->first();
                @endphp

                <div id="accordion">
                    <div class="card">
                        <a class="card-link" data-toggle="collapse" href="#collapseTwo">
                            <div class="card-header">
                                عضو في هيئة قضاء

                                @if ($dbc->state == 'WAITING')
                                    <span class="badge badge-info">قيد الإنتظار</span>
                                @endif

                                @if ($dbc->state == 'APPROVED')
                                    <span class="badge badge-success">مقبول</span>
                                @endif

                                @if ($dbc->state == 'DENIED')
                                    <span class="badge badge-danger">مرفوض</span>
                                @endif

                            </div>
                        </a>

                        @php
                            $labels = [ "إقتراح هيئة القضاء",
                                'استلام أمانة السر',
                                'VP إلى SG إرسال ',
                                "موافقة اللجان SGطلب ال",
                                "العلاقات العامة",
                                "امانة السر",
                                "لجنة المال",
                                "الموارد البشرية",
                                "ماكينة انتخابية",
                                "معلوماتية",
                                "انتشار",
                                "نشاطات ولوجستي",
                                "شؤون مرأة",
                                "شباب ورياضة",
                                "بلديات",
                                "ادارة ومراجعات",
                                "اعلام",
                                "VP ارسال الى",
                                'VP إلى SG إرسال نهائي من',
                                "VP موافقة",
                                'وتعميم GB من SG إستلام'];
                            @endphp

                        <div id="collapseTwo" class="collapse " data-parent="#accordion">
                            <div class="card-body">
                                <ul>
                                    @php
                                        $counter = 1;
                                    @endphp

                                    @foreach ($labels as $label)
                                        <li>
                                            <span>
                                                {{ $label }}
                                            </span>
                                            <span>
                                                {{ ($date = $dbc['phase_' . $counter]) ? $date : 'قيد الانتظار' }}
                                            </span>
                                        </li>
                                        @php
                                            $counter++;
                                        @endphp
                                    @endforeach

                                    <li>
                                        <span>
                                            رقم التعميم
                                        </span>
                                        <span>
                                            {{ ($date = $dbc->popularization_no) ? $date : 'قيد الانتظار' }}
                                        </span>
                                    </li>

                                </ul>
                            </div>
                        </div>


                    </div>

                </div>
                @endif


       <!--Local Body Member-->
       @if ($myLocalBodiesMembersApplication->count() > 0)

            @php
                $dbc = $myLocalBodiesMembersApplication->sortByDesc('id')->first();
            @endphp

            <div id="accordion">
                <div class="card">
                    <a class="card-link" data-toggle="collapse" href="#collapseThree">
                        <div class="card-header">
                            عضو في هيئة محلَية

                            @if ($dbc->state == 'WAITING')
                                <span class="badge badge-info">قيد الإنتظار</span>
                            @endif

                            @if ($dbc->state == 'APPROVED')
                                <span class="badge badge-success">مقبول</span>
                            @endif

                            @if ($dbc->state == 'DENIED')
                                <span class="badge badge-danger">مرفوض</span>
                            @endif

                        </div>
                    </a>

                    @php
                        $labels = [ "إقتراح الهيئة",
                        'موافقة القضاء',
                        'موافقة القطاع',
                        'إستلام أمانة السر',
                        'VP إلى SG إرسال',
                        "VP موافقة",
                        'VP إلى SG إرسال نهائي',
                        'وتعميم GB من SG إستلام',
                        ];
                        @endphp

                    <div id="collapseThree" class="collapse " data-parent="#accordion">
                        <div class="card-body">
                            <ul>
                                @php
                                    $counter = 1;
                                @endphp

                                @foreach ($labels as $label)
                                    <li>
                                        <span>
                                            {{ $label }}
                                        </span>
                                        <span>
                                            {{ ($date = $dbc['phase_' . $counter]) ? $date : 'قيد الانتظار' }}
                                        </span>
                                    </li>
                                    @php
                                        $counter++;
                                    @endphp
                                @endforeach

                                <li>
                                    <span>
                                        رقم التعميم
                                    </span>
                                    <span>
                                        {{ ($date = $dbc->popularization_no) ? $date : 'قيد الانتظار' }}
                                    </span>
                                </li>

                            </ul>
                        </div>
                    </div>


                </div>

            </div>
            @endif

            

       
       
       <!--Central Committee Coordinator-->
       @if ($myCentralCommitteesCoordinatorsApplication->count() > 0)

        @php
            $dbc = $myCentralCommitteesCoordinatorsApplication->sortByDesc('id')->first();
        @endphp

        <div id="accordion">
            <div class="card">
                <a class="card-link" data-toggle="collapse" href="#collapseFour">
                    <div class="card-header">
                            منسق لجنة مركزية

                        @if ($dbc->state == 'WAITING')
                            <span class="badge badge-info">قيد الإنتظار</span>
                        @endif

                        @if ($dbc->state == 'APPROVED')
                            <span class="badge badge-success">مقبول</span>
                        @endif

                        @if ($dbc->state == 'DENIED')
                            <span class="badge badge-danger">مرفوض</span>
                        @endif

                    </div>
                </a>

                @php
                    $labels = [   'VP لإقتراح SG إستلام ',
                'SG طلب تحضير التعمييم من ',
                'SG إستلام التعميم من قبل  ',
                'VP إلى SG إرسال التعميم من ',
                'على إصدار التعميم VP موافقة ال ',
                'VP إلى SG إرسال نهائي من',
                'وتعميم GB من SG إستلام',
                
                    ];
                    @endphp

                <div id="collapseFour" class="collapse " data-parent="#accordion">
                    <div class="card-body">
                        <ul>
                            @php
                                $counter = 1;
                            @endphp

                            @foreach ($labels as $label)
                                <li>
                                    <span>
                                        {{ $label }}
                                    </span>
                                    <span>
                                        {{ ($date = $dbc['phase_' . $counter]) ? $date : 'قيد الانتظار' }}
                                    </span>
                                </li>
                                @php
                                    $counter++;
                                @endphp
                            @endforeach

                            <li>
                                <span>
                                    رقم التعميم
                                </span>
                                <span>
                                    {{ ($date = $dbc->popularization_no) ? $date : 'قيد الانتظار' }}
                                </span>
                            </li>

                        </ul>
                    </div>
                </div>


            </div>

        </div>
        @endif





       
       
       
        <!--Central Committee Memeber-->
       @if ($myCentralCommitteesMemebersApplication->count() > 0)

            @php
                $dbc = $myCentralCommitteesMemebersApplication->sortByDesc('id')->first();
            @endphp

            <div id="accordion">
                <div class="card">
                    <a class="card-link" data-toggle="collapse" href="#collapseFive">
                        <div class="card-header">
                            عضو في لجنة مركزية 

                            @if ($dbc->state == 'WAITING')
                                <span class="badge badge-info">قيد الإنتظار</span>
                            @endif

                            @if ($dbc->state == 'APPROVED')
                                <span class="badge badge-success">مقبول</span>
                            @endif

                            @if ($dbc->state == 'DENIED')
                                <span class="badge badge-danger">مرفوض</span>
                            @endif

                        </div>
                    </a>

                    @php
                        $labels = [  
                'VP لإقتراح SG إستلام ',
                'SG طلب تحضير التعمييم من ',
                'SG إستلام التعميم من قبل  ',
                'VP إلى SG إرسال التعميم من ',
                'على إصدار التعميم VP موافقة ال ',
                'وتعميم GB من SG إستلام'
                    
                        ];
                        @endphp

                    <div id="collapseFive" class="collapse " data-parent="#accordion">
                        <div class="card-body">
                            <ul>
                                @php
                                    $counter = 1;
                                @endphp

                                @foreach ($labels as $label)
                                    <li>
                                        <span>
                                            {{ $label }}
                                        </span>
                                        <span>
                                            {{ ($date = $dbc['phase_' . $counter]) ? $date : 'قيد الانتظار' }}
                                        </span>
                                    </li>
                                    @php
                                        $counter++;
                                    @endphp
                                @endforeach

                                <li>
                                    <span>
                                        GB رقم التعميم
                                    </span>
                                    <span>
                                        {{ ($date = $dbc->popularization_no1) ? $date : 'قيد الانتظار' }}
                                    </span>
                                </li>
                                @php
                                    $labels = [  
                                            'VP موافقة',
                                            'على إصدار التعميم VP موافقة',
                                            'وتعميم GB من SG إستلام'
                                            ];
                                @endphp

                                @foreach ($labels as $label)
                                    <li>
                                        <span>
                                            {{ $label }}
                                        </span>
                                        <span>
                                            {{ ($date = $dbc['phase_' . $counter]) ? $date : 'قيد الانتظار' }}
                                        </span>
                                    </li>
                                    @php
                                        $counter++;
                                    @endphp
                                @endforeach
                                

                                <li>
                                    <span>
                                        GB رقم التعميم
                                    </span>
                                    <span>
                                        {{ ($date = $dbc->popularization_no2) ? $date : 'قيد الانتظار' }}
                                    </span>
                                </li>

                            </ul>
                        </div>
                    </div>


                </div>

            </div>
            @endif


            <!--myConsultingCommitteeMembersApplication-->
            @if ($myConsultingCommitteeMembersApplication->count() > 0)

            @php
                $dbc = $myConsultingCommitteeMembersApplication->sortByDesc('id')->first();
            @endphp

            <div id="accordion">
            <div class="card">
                <a class="card-link" data-toggle="collapse" href="#collapseSix">
                    <div class="card-header">
                            عضو لجنة إستشارية

                        @if ($dbc->state == 'WAITING')
                            <span class="badge badge-info">قيد الإنتظار</span>
                        @endif

                        @if ($dbc->state == 'APPROVED')
                            <span class="badge badge-success">مقبول</span>
                        @endif

                        @if ($dbc->state == 'DENIED')
                            <span class="badge badge-danger">مرفوض</span>
                        @endif

                    </div>
                </a>

                @php
                    $labels = [  
                        'موافقة الرئيس',
                        'موافقة نائب الرئيس'
                    ];
                    @endphp

                <div id="collapseSix" class="collapse " data-parent="#accordion">
                    <div class="card-body">
                        <ul>
                            @php
                                $counter = 1;
                            @endphp

                            @foreach ($labels as $label)
                                <li>
                                    <span>
                                        {{ $label }}
                                    </span>
                                    <span>
                                        {{ ($date = $dbc['phase_' . $counter]) ? $date : 'قيد الانتظار' }}
                                    </span>
                                </li>
                                @php
                                    $counter++;
                                @endphp
                            @endforeach

                            <li>
                                <span>
                                    رقم التعميم
                                </span>
                                <span>
                                    {{ ($date = $dbc->popularization_no) ? $date : 'قيد الانتظار' }}
                                </span>
                            </li>

                        </ul>
                    </div>
                </div>


            </div>

        </div>
            @endif

        </div>


















        <!--The Second Section-->
        <div class="box">
            <h2>طلبات من قمت بترشيح أسمائهم:</h2>
            

            <!--District coordinator-->
            @if ($othersDistrictBodyCoordinatorsApplications->count() > 0)

                @php
                    $dbc = $othersDistrictBodyCoordinatorsApplications->sortByDesc('id')->first();
                @endphp

                <div id="accordion">
                    <div class="card">
                        <a class="card-link" data-toggle="collapse" href="#secondCollapseeOne">
                        <div class="card-header">
                                منسق قضاء

                                @if ($dbc->state == 'WAITING')
                                    <span class="badge badge-info">قيد الإنتظار</span>
                                @endif

                                @if ($dbc->state == 'APPROVED')
                                    <span class="badge badge-success">مقبول</span>
                                @endif

                                @if ($dbc->state == 'DENIED')
                                    <span class="badge badge-danger">مرفوض</span>
                                @endif

                            </div>
                        </a>

                        @php
                            $labels = [' SG لاقتراح VP استلام', 'SG طلب تحضير التعميم من ', 'وضع التواقيع + SG استلام التعميم من', 'VP إلى SG إرسال التعميم من ', 'VP إلى SG إرسال نهائي من', 'على اصدار التعميم VP موافقى ال', 'وتعميم GB من SG إستلام'];
                        @endphp

                        <div id="secondCollapseOne" class="collapse " data-parent="#accordion">
                            <div class="card-body">
                                <ul>
                                    @php
                                        $counter = 1;
                                    @endphp

                                    @foreach ($labels as $label)
                                        <li>
                                            <span>
                                                {{ $label }}
                                            </span>
                                            <span>
                                                {{ ($date = $dbc['phase_' . $counter]) ? $date : 'قيد الانتظار' }}
                                            </span>
                                        </li>
                                        @php
                                            $counter++;
                                        @endphp
                                    @endforeach

                                    <li>
                                        <span>
                                            رقم التعميم
                                        </span>
                                        <span>
                                            {{ ($date = $dbc->popularization_no) ? $date : 'قيد الانتظار' }}
                                        </span>
                                    </li>

                                </ul>
                            </div>
                        </div>


                    </div>

                </div>
            @endif


            <!--District Body Memeber-->
            @if ($othersDistrictBodyMembersApplications->count() > 0)

                @php
                    $dbc = $othersDistrictBodyMembersApplications->sortByDesc('id')->first();
                @endphp

                <div id="accordion">
                    <div class="card">
                        <a class="card-link" data-toggle="collapse" href="#secondCollapseTwo">
                            <div class="card-header">
                            {{$dbc->candidate->name}} - عضو في هيئة قضاء 

                                @if ($dbc->state == 'WAITING')
                                    <span class="badge badge-info">قيد الإنتظار</span>
                                @endif

                                @if ($dbc->state == 'APPROVED')
                                    <span class="badge badge-success">مقبول</span>
                                @endif

                                @if ($dbc->state == 'DENIED')
                                    <span class="badge badge-danger">مرفوض</span>
                                @endif

                            </div>
                        </a>

                        @php
                            $labels = [ "إقتراح هيئة القضاء",
                                'استلام أمانة السر',
                                'VP إلى SG إرسال ',
                                "موافقة اللجان SGطلب ال",
                                "العلاقات العامة",
                                "امانة السر",
                                "لجنة المال",
                                "الموارد البشرية",
                                "ماكينة انتخابية",
                                "معلوماتية",
                                "انتشار",
                                "نشاطات ولوجستي",
                                "شؤون مرأة",
                                "شباب ورياضة",
                                "بلديات",
                                "ادارة ومراجعات",
                                "اعلام",
                                "VP ارسال الى",
                                'VP إلى SG إرسال نهائي من',
                                "VP موافقة",
                                'وتعميم GB من SG إستلام'];
                            @endphp

                        <div id="secondCollapseTwo" class="collapse " data-parent="#accordion">
                            <div class="card-body">
                                <ul>
                                    @php
                                        $counter = 1;
                                    @endphp

                                    @foreach ($labels as $label)
                                        <li>
                                            <span>
                                                {{ $label }}
                                            </span>
                                            <span>
                                                {{ ($date = $dbc['phase_' . $counter]) ? $date : 'قيد الانتظار' }}
                                            </span>
                                        </li>
                                        @php
                                            $counter++;
                                        @endphp
                                    @endforeach

                                    <li>
                                        <span>
                                            رقم التعميم
                                        </span>
                                        <span>
                                            {{ ($date = $dbc->popularization_no) ? $date : 'قيد الانتظار' }}
                                        </span>
                                    </li>

                                </ul>
                            </div>
                        </div>


                    </div>

                </div>
                @endif


       <!--Local Body Member-->
       @if ($othersLocalBodiesMembersApplication->count() > 0)

            @php
                $dbc = $othersLocalBodiesMembersApplication->sortByDesc('id')->first();
            @endphp

            <div id="accordion">
                <div class="card">
                    <a class="card-link" data-toggle="collapse" href="#secondCollapseThree">
                        <div class="card-header">
                            {{$dbc->candidate->name}} - عضو في هيئة محلَية

                            @if ($dbc->state == 'WAITING')
                                <span class="badge badge-info">قيد الإنتظار</span>
                            @endif

                            @if ($dbc->state == 'APPROVED')
                                <span class="badge badge-success">مقبول</span>
                            @endif

                            @if ($dbc->state == 'DENIED')
                                <span class="badge badge-danger">مرفوض</span>
                            @endif

                        </div>
                    </a>

                    @php
                        $labels = [ "إقتراح الهيئة",
                        'موافقة القضاء',
                        'موافقة القطاع',
                        'إستلام أمانة السر',
                        'VP إلى SG إرسال',
                        "VP موافقة",
                        'VP إلى SG إرسال نهائي',
                        'وتعميم GB من SG إستلام',
                        ];
                        @endphp

                    <div id="secondCollapseThree" class="collapse " data-parent="#accordion">
                        <div class="card-body">
                            <ul>
                                @php
                                    $counter = 1;
                                @endphp

                                @foreach ($labels as $label)
                                    <li>
                                        <span>
                                            {{ $label }}
                                        </span>
                                        <span>
                                            {{ ($date = $dbc['phase_' . $counter]) ? $date : 'قيد الانتظار' }}
                                        </span>
                                    </li>
                                    @php
                                        $counter++;
                                    @endphp
                                @endforeach

                                <li>
                                    <span>
                                        رقم التعميم
                                    </span>
                                    <span>
                                        {{ ($date = $dbc->popularization_no) ? $date : 'قيد الانتظار' }}
                                    </span>
                                </li>

                            </ul>
                        </div>
                    </div>


                </div>

            </div>
            @endif

            

       
            
       <!--Central Committee Coordinator-->
       @if ($othersCentralCommitteesCoordinatorsApplication->count() > 0)

        @php
            $dbc = $othersCentralCommitteesCoordinatorsApplication->sortByDesc('id')->first();
        @endphp

        <div id="accordion">
            <div class="card">
                <a class="card-link" data-toggle="collapse" href="#secondCollapseFour">
                    <div class="card-header">
                          {{$dbc->candidate->name}} - منسق لجنة مركزية

                        @if ($dbc->state == 'WAITING')
                            <span class="badge badge-info">قيد الإنتظار</span>
                        @endif

                        @if ($dbc->state == 'APPROVED')
                            <span class="badge badge-success">مقبول</span>
                        @endif

                        @if ($dbc->state == 'DENIED')
                            <span class="badge badge-danger">مرفوض</span>
                        @endif

                    </div>
                </a>

                @php
                    $labels = [   'VP لإقتراح SG إستلام ',
                                'SG طلب تحضير التعمييم من ',
                                'SG إستلام التعميم من قبل  ',
                                'VP إلى SG إرسال التعميم من ',
                                'على إصدار التعميم VP موافقة ال ',
                                'VP إلى SG إرسال نهائي من',
                                'وتعميم GB من SG إستلام',
                                  ];
                @endphp

                <div id="secondCollapseFour" class="collapse " data-parent="#accordion">
                    <div class="card-body">
                        <ul>
                            @php
                                $counter = 1;
                            @endphp

                            @foreach ($labels as $label)
                                <li>
                                    <span>
                                        {{ $label }}
                                    </span>
                                    <span>
                                        {{ ($date = $dbc['phase_' . $counter]) ? $date : 'قيد الانتظار' }}
                                    </span>
                                </li>
                                @php
                                    $counter++;
                                @endphp
                            @endforeach

                            <li>
                                <span>
                                    رقم التعميم
                                </span>
                                <span>
                                    {{ ($date = $dbc->popularization_no) ? $date : 'قيد الانتظار' }}
                                </span>
                            </li>

                        </ul>
                    </div>
                </div>


            </div>

        </div>
        @endif





       
        
        <!--Central Committee Memeber-->
       @if ($othersCentralCommitteesMemebersApplication->count() > 0)

            @php
                $dbc = $othersCentralCommitteesMemebersApplication->sortByDesc('id')->first();
            @endphp

            <div id="accordion">
                <div class="card">
                    <a class="card-link" data-toggle="collapse" href="#secondCollapseFive">
                        <div class="card-header">
                        {{$dbc->candidate->name}} - عضو في لجنة مركزية

                            @if ($dbc->state == 'WAITING')
                                <span class="badge badge-info">قيد الإنتظار</span>
                            @endif

                            @if ($dbc->state == 'APPROVED')
                                <span class="badge badge-success">مقبول</span>
                            @endif

                            @if ($dbc->state == 'DENIED')
                                <span class="badge badge-danger">مرفوض</span>
                            @endif

                        </div>
                    </a>

                    @php
                        $labels = [  
                            'VP لإقتراح SG إستلام ',
                            'SG طلب تحضير التعمييم من ',
                            'SG إستلام التعميم من قبل  ',
                            'VP إلى SG إرسال التعميم من ',
                            'على إصدار التعميم VP موافقة ال ',
                            'وتعميم GB من SG إستلام'
                        ];
                        @endphp

                    <div id="secondCollapseFive" class="collapse " data-parent="#accordion">
                        <div class="card-body">
                            <ul>
                                @php
                                    $counter = 1;
                                @endphp

                                @foreach ($labels as $label)
                                    <li>
                                        <span>
                                            {{ $label }}
                                        </span>
                                        <span>
                                            {{ ($date = $dbc['phase_' . $counter]) ? $date : 'قيد الانتظار' }}
                                        </span>
                                    </li>
                                    @php
                                        $counter++;
                                    @endphp
                                @endforeach

                                <li>
                                    <span>
                                        GB رقم التعميم
                                    </span>
                                    <span>
                                        {{ ($date = $dbc->popularization_no1) ? $date : 'قيد الانتظار' }}
                                    </span>
                                </li>

                                $labels = [  
                                        'VP موافقة',
                                        'على إصدار التعميم VP موافقة',
                                        'وتعميم GB من SG إستلام'
                                        ];
                                
                                @foreach ($labels as $label)
                                    <li>
                                        <span>
                                            {{ $label }}
                                        </span>
                                        <span>
                                            {{ ($date = $dbc['phase_' . $counter]) ? $date : 'قيد الانتظار' }}
                                        </span>
                                    </li>
                                    @php
                                        $counter++;
                                    @endphp
                                @endforeach
                                

                                <li>
                                    <span>
                                        GB رقم التعميم
                                    </span>
                                    <span>
                                        {{ ($date = $dbc->popularization_no2) ? $date : 'قيد الانتظار' }}
                                    </span>
                                </li>

                            </ul>
                        </div>
                    </div>


                </div>

            </div>
            @endif
        </div>

    
    </main>
</body>

</html>

