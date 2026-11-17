{{-- Round member photos in the council tables. A member without a photo gets a grey silhouette (window.MPH). --}}
<style>
    .mphoto { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; object-position: center top; border: 2px solid #d6dde3; background: #dfe5ea; display: block; }
    td .mphoto { margin: 0 auto; }
</style>
<style>
    .mphoto { cursor: zoom-in; }
    .mphoto.is-placeholder { cursor: default; }
    #mphotoBox { position: fixed; inset: 0; z-index: 99999; display: none; align-items: center; justify-content: center; flex-direction: column;
                 background: rgba(0, 20, 35, .82); cursor: zoom-out; }
    #mphotoBox.open { display: flex; }
    #mphotoBox img { max-width: min(92vw, 720px); max-height: 80vh; border-radius: 14px; border: 4px solid #fff; box-shadow: 0 20px 60px rgba(0, 0, 0, .6); background: #fff; }
    #mphotoBox .cap { margin-top: 14px; color: #fff; font-size: 22px; font-weight: 600; text-align: center; padding: 0 16px; }
</style>
<div id="mphotoBox" aria-hidden="true"><img alt=""><div class="cap" dir="auto"></div></div>
<script>
    // click a member photo to see it big; click anywhere or press Esc to close
    (function () {
        var box = document.getElementById('mphotoBox'), big = box.querySelector('img'), cap = box.querySelector('.cap');
        function close() { box.classList.remove('open'); big.removeAttribute('src'); }
        document.addEventListener('click', function (e) {
            var img = e.target.closest && e.target.closest('img.mphoto');
            if (!img) { if (box.classList.contains('open')) { close(); } return; }
            if (img.src === window.MPH) { img.classList.add('is-placeholder'); return; }   // no photo on file
            var tr = img.closest('tr'), nameCell = tr && tr.querySelector('td[dir="auto"]');
            cap.textContent = (tr && tr.getAttribute('data-name')) || (nameCell ? nameCell.textContent.trim() : '');
            big.src = img.src;
            box.classList.add('open');
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { close(); } });
    })();
</script>
<script>
    window.MPH = 'data:image/svg+xml;base64,{{ base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" fill="#dfe5ea"/><circle cx="32" cy="25" r="11" fill="#9aa7b2"/><path d="M10 64c2-15 12-22 22-22s20 7 22 22z" fill="#9aa7b2"/></svg>') }}';
</script>
