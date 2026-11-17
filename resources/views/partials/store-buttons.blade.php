{{-- Download-the-app block shared by the public news / event pages.
     A visitor who has the app never sees this page (the OS opens the app from the
     verified link); one who does not gets a single big button that goes to the
     store of THEIR phone (App Store on iPhone/iPad, Google Play otherwise), plus
     both store links underneath. --}}
<style>
    .get-app {
        display: block;
        background-color: #f68f1f;
        color: #fff;
        text-align: center;
        padding: 16px;
        border-radius: 8px;
        font-size: 18px;
        font-weight: bold;
        text-decoration: none;
        margin: 8px 0 14px;
    }
    .stores-note { text-align: center; font-size: 14px; color: #666; margin-bottom: 10px; }
    .stores { display: flex; gap: 18px; justify-content: center; font-size: 15px; }
    .stores a { color: #035a8b; }
</style>

<a id="get-app" class="get-app" href="https://play.google.com/store/apps/details?id=com.microbits.fpm">حمّل تطبيق التيار الوطني الحر</a>
<p class="stores-note">متوفر على</p>
<div class="stores">
    <a href="https://play.google.com/store/apps/details?id=com.microbits.fpm">Google Play</a>
    <a href="https://apps.apple.com/app/id1083351430">App Store</a>
</div>

<script>
    // iPhone / iPad / iPod -> App Store; everything else keeps the Google Play default
    (function () {
        var ua = navigator.userAgent || '';
        if (/iPhone|iPad|iPod/i.test(ua)) {
            document.getElementById('get-app').href = 'https://apps.apple.com/app/id1083351430';
        }
    })();
</script>
