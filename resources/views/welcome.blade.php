<!DOCTYPE html>
<html lang="en">

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
    <link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700|Roboto:300,400,400i,500,500i,700,700i,900,900i" rel="stylesheet">
    <!-- CSS -->

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-BmbxuPwQa2lc/FVzBcNJ7UAyJxM6wuqIj61tLrc4wSX0szH/Ev+nYRRuWlolflfl" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta2/dist/js/bootstrap.bundle.min.js" integrity="sha384-b5kHyXgcpbZJO/tY9Ul7kGkf1S0CWuKcCD38l8YkeH8z8QjE0GmW1gYU5S9FOnJ0" crossorigin="anonymous"></script>
    <!-- Favicon -->
    <link rel="shortcut icon" type="image/png" href="{{url('landing/img/favicon.png')}}">

    <style>
        .w-30 {
            width: 30% !important;
        }

        body {

            background:#f9f9f9;
        }

        .container{
            height: 100vh !important;
            display: flex;
            justify-content: center;
        }
    </style>
</head>

<body>
    <div class="container-wrapper h-100 py-md-5">
        <div class="container h-100">
            <div class="w-100 p-5 d-flex flex-column align-items-center justify-content-center ">

                <div class="row justify-content-center">
                    <div class="col-md-4 col-sm-12 d-flex justify-content-center align-items-center p-4">
                        <img src="{{url('landing/img/fpm-logo.svg')}}" class="w-100" alt="logo">
                    </div>
                </div>

                <div class="row justify-content-center mb-4">
                    <div class="col-md-4 col-sm-12 d-flex justify-content-center p-4">
                        <img class="w-75" src="{{url('landing/img/app.svg')}}" class="w-25" alt="">
                    </div>
                </div>

                <p class="title text-center mb-2">DOWNLOAD <b>FPM APP</b></p>

                <div class="row justify-content-center">
                    <div class="col col-md-5 col-sm-12 justify-content-center">
                        <div class="d-flex justify-content-center">
                            @php
                                $agent = strtoupper(request()->header('User-Agent'));
                            @endphp

                            @if(str_contains($agent,"IOS"))
                                <a class="w-50 m-2" href="https://apps.apple.com/lb/app/fpm-app/id1083351430">
                                    <img class="w-100" src="{{asset('/images/app_store.svg')}}">
                                </a>
                            @elseif(str_contains($agent,"ANDROID"))
                                <a class="w-50 m-2" href="https://play.google.com/store/apps/details?id=com.microbits.fpm&hl=en&gl=US">
                                    <img class="w-100" src="{{asset('/images/google_play.svg')}}">
                                </a>
                            @else
                                <a  class="w-50 m-2" href="https://apps.apple.com/lb/app/fpm-app/id1083351430">
                                    <img class="w-100 " src="{{asset('/images/app_store.svg')}}">
                                </a>
                                <a  class="w-50 m-2" href="https://play.google.com/store/apps/details?id=com.microbits.fpm&hl=en&gl=US">
                                    <img class="w-100" src="{{asset('/images/google_play.svg')}}">
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
             </div>
         </div>
    </div>
</body>

</html>
