{{-- Round member photos in the council tables. A member without a photo gets a grey silhouette (window.MPH). --}}
<style>
    .mphoto { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; object-position: center top; border: 2px solid #d6dde3; background: #dfe5ea; display: block; }
    td .mphoto { margin: 0 auto; }
</style>
<script>
    window.MPH = 'data:image/svg+xml;base64,{{ base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" fill="#dfe5ea"/><circle cx="32" cy="25" r="11" fill="#9aa7b2"/><path d="M10 64c2-15 12-22 22-22s20 7 22 22z" fill="#9aa7b2"/></svg>') }}';
</script>
