<!DOCTYPE html>
<html dir="rtl">

<head>
    <!--- Basic Page Needs  -->
    <meta charset="utf-8">
    <title>Welcome to the FPM Mobile Application</title>
    <meta name="description" content="">
    <meta name="author" content="">
    <meta name="keywords" content="">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <!-- Mobile Specific Meta  -->
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Scheherazade:wght@400;700&display=swap" rel="stylesheet">


    <!-- CSS -->
    <link rel="stylesheet" href="{{url('landing/css/bootstrap.min.css')}}">
    <link rel="stylesheet" href="{{url('landing/css/font-awesome.min.css')}}">
    <link rel="stylesheet" href="{{url('landing/css/owl.carousel.css')}}">
    <link rel="stylesheet" href="{{url('landing/css/style.css')}}">
    <link rel="stylesheet" href="{{url('landing/css/responsive.css')}}">
    <link rel="stylesheet" href="{{url('css/transaction.css')}}">

 
    <!-- Favicon -->
    <link rel="shortcut icon" type="image/png" href="{{url('landing/img/favicon.png')}}">
    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
    <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
    <![endif]-->

</head>

<body data-spy="scroll" data-target="#scroll-menu" data-offset="100" class="transaction-bg">
<div id="preloader"></div>


<div class="mb-5">
    <img src="{{$image}}" width="100%" class="img-fluid transaction-cover-image" alt="Responsive image" >
</div>

<div class="container p-5 transaction-direction">

    <div class="text-center" style="margin-bottom: 3em; margin-top: 3em">

        <div class="transaction-title">
            <h1 class="transaction-title"> المعلومات الشخصيه</h1>
           
        </div>

        <div class="">
            <form method="POST" action="{{route('transactions.store')}}">
                @csrf

                <div class="form-group ">
                    <input type="text" class="form-control transaction-input " id="user_name" name="user_name" placeholder="الاسم" value="{{ old('user_name') }}">
                    <small class="text-danger ">  {{$errors->has('user_name') ? 'الحقل اسم الزامي' : ''}}</small>
                </div>

                <div class="form-group">
                    <input type="text" class="form-control transaction-input" id="phone_number" name="phone_number" placeholder="رقم الهاتف" value="{{ old('phone_number') }}">
                    <small class="text-danger ">  {{$errors->has('phone_number') ? 'الحقل رقم الزامي' : ''}}</small>
                </div>

                <div class="form-group">
                    <input type="email" class="form-control transaction-input" id="email" name="email" placeholder="البريد الالكتروني" value="{{ old('email') }}">
                    <small class="text-danger ">  {{$errors->has('email') ? 'الحقل بريد الكتروني الزامي' : ''}}</small>
                </div>


                <div class="flex">
                    <p  class="transaction-label " style="margin-left: 2em"><b>العمله</b></p>
                    <label class="radio-container">
                        دولار
                        <input type="radio" checked="checked" class="currency" value="USD" name="currency">
                        <span class="checkmark"></span>
                    </label>
                    
                    <label class="radio-container margin-left-30">
                        ليره
                        <input type="radio" checked="checked" class="currency" name="currency" value="LBP">
                        <span class="checkmark"></span>
                    </label>
                </div>

                <small class="text-danger ">  {{$errors->has('user_name') ? 'الحقل عمله الزامي' : ''}}</small>

                <div class="amount-container ">
                    <div class="USD transaction-box">
                        <div class="form-group" >
                            <select name="usd_amount" class="form-control dropdown" placeholder="dddd" id="usd_values">
                                <option value=""> </option>
                                @foreach($usd_values as $usd)
                                    <option value="{{$usd}}">${{$usd}}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="LBP transaction-box">
                        <div class="form-group" >
                            <select name="lbp_amount"   class="form-control dropdown " id="lbp_values">
                                <option value=""></option>
                                @foreach($lbp_values as $lbp)
                                    <option value="{{$lbp}}">{{$lbp}} LBP</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <p  class="transaction-label amount">حدد المبلغ </p>
                </div>

                <small class="text-danger ">  {{session()->has('error') ? session()->get('error') : ''}}</small>




                <div class="form-group">
                    <p  class="transaction-label"><b>شكرا جزيلا على دعكمكم للتيار. يرجى تحديد طبيعه هذا التبرع:</b></p>
               
                        <div class="period-container">
                            <label class="radio-container">
                                <input type="radio" checked="checked"  id="payment_type_1"  value="once"  name="payment_type">
                                لمرة واحدة
                                <span class="checkmark"></span>
                            </label>
                        
                            <label class="radio-container">
                                شهرياً
                                <input type="radio" checked="checked" id="payment_type_2" name="payment_type" value="monthly">
                                <span class="checkmark"></span>
                            </label>

                        </div>
                    <small class="text-danger ">  {{$errors->has('payment_type') ? 'الحقل نوع العمليه الزامي' : ''}}</small>
                </div>


                <button type="submit" class="btn btn-block transaction-button">DONATE NOW</button>

                <div class="text-center">
                    <img src="{{asset('/images/payments_by_netcommerce.gif')}}" width="180" alt="">
                </div>
            </form>
        </div>
    </div>
</div>





<!--footer area end-->

<!--Copyright area start-->
<footer>
    <p> &copy; التيار الوطني الحر. جميع الحقوق محفوظة 2020</p>

</footer>
<!--Copyright area end-->

<!--Back to top area-->
<a id="back-to-top" href=""><i class="fa fa-long-arrow-up" aria-hidden="true"></i></a>
<!--Back to top area-->

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
    $(document).ready(function(){
        $('.LBP').show();
        $('.currency').click(function(){
            var inputValue = $(this).attr("value");
     
            var targetBox = $("." + inputValue);
            $(".transaction-box").not(targetBox).hide();
            $(targetBox).show();
        });

        $('.amount').click(function(){
            
            $('select').trigger('open');
        })
    });
</script>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js" integrity="sha384-JEW9xMcG8R+pH31jmWH6WWP0WintQrMb4s7ZOdauHnUtxwoG2vI5DkLtS3qm9Ekf" crossorigin="anonymous"></script>
<script src="{{url('landing/js/SmoothScroll.min.js')}}"></script>
<script src="{{url('landing/js/typed.min.js')}}"></script>
<script src="{{url('landing/js/jquery.enllax.min.js')}}"></script>

<script src="{{url('landing/js/vue-carousel-3d.min.js')}}"></script>
<script src="{{url('landing/js/owl.carousel.min.js')}}"></script>
<script src="{{url('landing/js/theme.js')}}"></script>
<!-- Scripts -->
</body>

</html>


