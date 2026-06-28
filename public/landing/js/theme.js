// -----------------------------
//   js index
/* =================== */
/* 	
 */
// -----------------------------

(function ($) {
    "use strict";

    var winDo = $(window);
    /*------------------------
      Landing Page one Screenshot slider carousel
     ------------------------ */
    $(".screenshots-slider-carousel").owlCarousel({
        autoPlay: true, //Set AutoPlay to 3 seconds
        nav: true,
        items: 3,
        itemsDesktop: [1199, 3],
        itemsDesktopSmall: [979, 3],
        itemsTablet: [768, 1],
        itemsMobile: [479, 1]
    });


    /*---------------------
    Home Slider Section
    --------------------- */
    $('.home-slider').owlCarousel({
        animateOut: 'slideOutDown',
        animateIn: 'flipInX',
        autoPlay: true, //Set AutoPlay to 3 seconds
        items: 1,
        pagination: true,
        itemsDesktop: [1199, 1],
        itemsDesktopSmall: [979, 1],
        itemsTablet: [768, 1],
        itemsMobile: [479, 1]
    });

    /*---------------------
    Feedback Slide Section
    --------------------- */
    $(".client-feedback").owlCarousel({
        autoPlay: true, //Set AutoPlay to 3 seconds
        items: 1,
        pagination: false,
        itemsDesktop: [1199, 1],
        itemsDesktopSmall: [979, 1],
        itemsTablet: [768, 1],
        itemsMobile: [479, 1]
    });

    /*---------------------------
    Feedback Slide Section Second
    --------------------------- */
    $(".client-feedback-sceond").owlCarousel({
        autoPlay: true, //Set AutoPlay to 3 seconds
        items: 1,
        pagination: false,
        itemsDesktop: [1199, 1],
        itemsDesktopSmall: [979, 1],
        itemsTablet: [768, 1],
        itemsMobile: [479, 1]
    });

    /*---------------------
    Preloader
    --------------------- */

   winDo.on('load', function () {
        $('#preloader').fadeOut('slow', function () {
            $(this).remove();
        });
    });


    /*-----------------------------
    3D Apss Slider for Screenshot 
    ------------------------------*/
    new Vue({
        el: '#apps-Slide',
        data: {
            slides: 7
        },
        components: {
            'carousel-3d': Carousel3d.Carousel3d,
            'slide': Carousel3d.Slide
        }
    });
    /*------------------------
      Smooth Scroll for Navbar
     ------------------------*/
    $('a[href*="#"]')
        .not('[href="#"]')
        .not('[href="#0"]')
        .click(function (event) {
            if (
                location.pathname.replace(/^\//, '') === this.pathname.replace(/^\//, '') &&
                location.hostname === this.hostname
            ) {
                var target = $(this.hash);
                target = target.length ? target : $('[name=' + this.hash.slice(1) + ']');
                if (target.length) {
                    event.preventDefault();
                    $('html, body').animate({
                        scrollTop: target.offset().top
                    }, 1000, function () {
                        var $target = $(target);
                        $target.focus();
                        if ($target.is(":focus")) {
                            return false;
                        } else {
                            $target.attr('tabindex', '-1');
                            $target.focus();
                        };
                    });
                }
            }
        });

    /*------------------------
      Parallax Activation
     ------------------------*/
    winDo.enllax();

    var backtop = $('#back-to-top');
    /*-----------------
    Back To Top
    -----------------*/
    if (backtop.length) {
        var scrollTrigger = 150, // px
            backToTop = function () {
                var scrollTop = winDo.scrollTop();
                if (scrollTop > scrollTrigger) {
                    backtop.addClass('show');
                } else {
                    backtop.removeClass('show');
                }
            };
        backToTop();
        winDo.on('scroll', function () {
            backToTop();
        });
        backtop.on('click', function (e) {
            e.preventDefault();
            $('html,body').animate({
                scrollTop: 0
            }, 700);
        });

    }

    /*------------------------
      Navbar Fixed 
      ------------------------*/
    var navbarfix = $('body.ad-navbar-fixed');
    
    
    winDo.on('scroll', function () {
        var nav = $('#apps_home .header-navbar-fixed');
        var top = 150;
        if (winDo.scrollTop() >= top) {
            navbarfix.css({
                "marginTop": "150px"
            });
            nav.addClass('navbar-fixed-top');

        } else {
           navbarfix.css({
                "marginTop": "0px"
            });
            nav.removeClass('navbar-fixed-top');
        }

    });

    /*------------------------
     Type Style & Activatiopn
     ------------------------*/
    $(".typed span").typed({
        strings: ["Best App. <br>in the World !", "Install Now. <br> The AD.World!", "Review. <br>Make feedback!"],
        stringsElement: null,
        typeSpeed: 30,
        startDelay: 1200,
        backSpeed: 20,
        backDelay: 10,
        loop: true,
        loopCount: 555,
        showCursor: false,
        cursorChar: "|",
        attr: null,
        contentType: 'html'
    });


    $(".typed-contents span").typed({
        strings: ["Do Hurry! to Download this app.Otherwise you are loser From the world latset feature .", "It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages,"],
        stringsElement: null,
        typeSpeed: 10,
        startDelay: 600,
        backSpeed: 10,
        backDelay: 10,
        loop: true,
        loopCount: 555,
        showCursor: false,
        cursorChar: ">",
        attr: null,
        contentType: 'html'
    });



    $(".coming-soon-title").typed({
        strings: ["We are Coming Soon . . .", "Make AD.app service . . ."],
        stringsElement: null,
        typeSpeed: 10,
        startDelay: 600,
        backSpeed: 10,
        backDelay: 10,
        loop: true,
        loopCount: 555,
        showCursor: false,
        cursorChar: ">",
        attr: null,
        contentType: 'html'
    });
    /*------------------------
     Popup Modal video stop
     ------------------------*/
    var ibodyframe = $('body');
    var ifmremodal =  $("#myModal iframe");
    
    $(document).on("click", function (e) {
        setTimeout(function () {
            if (!ibodyframe.hasClass('modal-open')) {
                ifmremodal.attr("src", ifmremodal.attr("src"));
            }
        }, 1000);
    });
    $('.btn-play').on("click", function () {
        setTimeout(function () {
            ibodyframe.animate({
                "paddingRight": "0px"
            }, 300);
        }, 20);
    });



    /*------------------------
     Countdown
     ------------------------*/

    $('#countdown').countdown('2018/01/02', function (event) {
        var $this = $(this);
        $this.find('#day').html(event.strftime('<span>%D</span>'));
        $this.find('#hour').html(event.strftime('<span>%H</span>'));
        $this.find('#month').html(event.strftime('<span>%M</span>'));
        $this.find('#second').html(event.strftime('<span>%S</span>'));
    });


}(jQuery));