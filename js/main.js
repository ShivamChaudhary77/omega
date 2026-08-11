(function ($) {
    "use strict";

    // Spinner
    var spinner = function () {
        setTimeout(function () {
            if ($('#spinner').length > 0) {
                $('#spinner').removeClass('show');
            }
        }, 1);
    };
    spinner();
    
    
    // Initiate the wowjs
    new WOW().init();


    // Sticky Navbar
    $(window).scroll(function () {
        if ($(this).scrollTop() > 300) {
            $('.sticky-top').addClass('bg-white shadow-sm').css('top', '0px');
        } else {
            $('.sticky-top').removeClass('bg-white shadow-sm').css('top', '-150px');
        }
    });
    
    
    // Back to top button
    $(window).scroll(function () {
        if ($(this).scrollTop() > 100) {
            $('.back-to-top').fadeIn('slow');
        } else {
            $('.back-to-top').fadeOut('slow');
        }
    });
    $('.back-to-top').click(function () {
        $('html, body').animate({scrollTop: 0}, 1500, 'easeInOutExpo');
        return false;
    });


    // Fixed WhatsApp CTA — injected here (rather than hard-coded per page)
    // so every page on the site gets it without editing 90+ static HTML
    // files individually. js/analytics.js already tracks any a[href*="wa.me"]
    // click as the whatsapp_click GA4 event, so no extra wiring is needed.
    if ($('.whatsapp-float').length === 0) {
        $('body').append(
            '<a href="https://wa.me/919811001900" class="whatsapp-float" target="_blank" rel="noopener" aria-label="Chat with The Omega Group on WhatsApp"><i class="fab fa-whatsapp"></i></a>'
        );
    }


    // Pages without any carousel markup (e.g. the blog) don't load the Owl
    // Carousel library at all, so skip these inits rather than throw.
    if (!$.fn.owlCarousel) {
        return;
    }


    // Header carousel
    $(".header-carousel").owlCarousel({
        autoplay: true,
        smartSpeed: 1000,
        loop: true,
        dots: true,
        items: 1
    });


    // Latest Projects carousel
    $(".project-carousel").owlCarousel({
        loop: true,
        margin: 24,
        autoplay: true,
        autoplayTimeout: 3500,
        autoplayHoverPause: true,
        smartSpeed: 800,
        dots: false,
        nav: true,
        navElement: 'button',
        navText: [
            '<i class="bi bi-chevron-left"></i>',
            '<i class="bi bi-chevron-right"></i>'
        ],
        responsive: {
            0: {
                items: 1,
                dots: true
            },
            576: {
                items: 2,
                dots: false
            },
            992: {
                items: 3,
                dots: false
            }
        }
    });


    // Testimonials carousel
    $(".testimonial-carousel").owlCarousel({
        items: 1,
        autoplay: true,
        smartSpeed: 1000,
        animateIn: 'fadeIn',
        animateOut: 'fadeOut',
        dots: true,
        loop: true,
        nav: false
    });
    
})(jQuery);

