@extends($layout)
@section('content')
<div class="box box-default">
    <div class="box-header with-border">
        <h3 class="box-title">{{ $pageTitle }}</h3>
    </div>
    <div class="box-body">
        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif

        <p>
            <a href="{{ route('admin.internal-election.import-allowed-voters-template') }}" class="btn btn-default">
                <i class="fa fa-download"></i> تحميل نموذج Excel
            </a>
        </p>

        <form method="POST" action="{{ route('admin.internal-election.import-allowed-voters-store') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label>ملف Excel (.xlsx)</label>
                <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required>
                <p class="help-block">عمود واحد فقط: رقم الانتساب (Member ID). كل عضو موجود في الملف سيتم السماح له بالتصويت في جميع الإنتخابات الداخلية — الأعضاء غير الموجودين في الملف لا يتأثرون.</p>
            </div>
            <button type="submit" class="btn btn-success">استيراد</button>
            <a href="{{ route('admin.internal-election.index') }}" class="btn btn-default">إلغاء</a>
        </form>

        <hr>

        <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#resetAllowedToVoteModal">
            إعادة تعيين الجميع إلى 0
        </button>
        <p class="help-block" style="margin-top:8px;">يلغي حق التصويت عن <strong>جميع</strong> الأعضاء دفعة واحدة (رجوعاً إلى الوضع الإفتراضي) — استخدم هذا قبل استيراد لائحة جديدة إذا أردت استبدال اللائحة القديمة بالكامل بدل الإضافة عليها.</p>
    </div>
</div>

<div class="modal fade" id="resetAllowedToVoteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تأكيد إعادة التعيين</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div id="reset-step-1">
                    <p class="text-danger"><strong>تحذير:</strong> سيتم إلغاء حق التصويت عن جميع الأعضاء في النظام دفعة واحدة. هذا الإجراء لا يمكن التراجع عنه تلقائياً — يجب استيراد اللائحة من جديد لاستعادة الوضع.</p>
                    <p>هل أنت متأكد أنك تريد المتابعة؟</p>
                </div>

                <div id="reset-step-2" style="display:none;">
                    <p>للتأكيد النهائي، اسحب الزر بالكامل إلى النهاية:</p>
                    <div class="reset-slide-track" id="reset-slide-track">
                        <div class="reset-slide-label">اسحب للتأكيد</div>
                        <div class="reset-slide-handle" id="reset-slide-handle">&#8594;</div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-danger" id="reset-step-1-continue">متابعة</button>
            </div>
        </div>
    </div>
</div>

<form id="reset-allowed-to-vote-form" method="POST" action="{{ route('admin.internal-election.reset-allowed-to-vote') }}" style="display:none;">
    @csrf
</form>

<style>
    .reset-slide-track {
        position: relative;
        width: 100%;
        height: 46px;
        background-color: #f5f5f5;
        border: 1px solid #ddd;
        border-radius: 4px;
        overflow: hidden;
        user-select: none;
    }
    .reset-slide-label {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #999;
        font-size: 13px;
        pointer-events: none;
    }
    .reset-slide-handle {
        position: absolute;
        top: 2px;
        left: 2px;
        width: 42px;
        height: 40px;
        background-color: #dc3545;
        color: #fff;
        border-radius: 3px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: grab;
        font-weight: bold;
        touch-action: none;
    }
    .reset-slide-track.confirmed .reset-slide-handle {
        background-color: #28a745;
        cursor: default;
    }
</style>

<script>
(function () {
    var modal = document.getElementById('resetAllowedToVoteModal');
    var step1 = document.getElementById('reset-step-1');
    var step2 = document.getElementById('reset-step-2');
    var continueBtn = document.getElementById('reset-step-1-continue');
    var track = document.getElementById('reset-slide-track');
    var handle = document.getElementById('reset-slide-handle');
    var form = document.getElementById('reset-allowed-to-vote-form');

    function resetModalState() {
        step1.style.display = '';
        step2.style.display = 'none';
        continueBtn.style.display = '';
        handle.style.left = '2px';
        track.classList.remove('confirmed');
        dragging = false;
    }

    if (modal) {
        modal.addEventListener('hidden.bs.modal', resetModalState);
    }

    continueBtn.addEventListener('click', function () {
        step1.style.display = 'none';
        step2.style.display = '';
        continueBtn.style.display = 'none';
    });

    var dragging = false;
    var startX = 0;
    var startLeft = 2;

    function pointerX(e) {
        return (e.touches && e.touches.length) ? e.touches[0].clientX : e.clientX;
    }

    function onDragStart(e) {
        if (track.classList.contains('confirmed')) return;
        dragging = true;
        startX = pointerX(e);
        startLeft = handle.offsetLeft;
        handle.style.cursor = 'grabbing';
    }

    function onDragMove(e) {
        if (!dragging) return;
        var maxLeft = track.clientWidth - handle.offsetWidth - 2;
        var delta = pointerX(e) - startX;
        var newLeft = Math.min(Math.max(startLeft + delta, 2), maxLeft);
        handle.style.left = newLeft + 'px';

        if (newLeft >= maxLeft - 2) {
            dragging = false;
            track.classList.add('confirmed');
            handle.innerHTML = '&#10003;';
            setTimeout(function () { form.submit(); }, 200);
        }
    }

    function onDragEnd() {
        if (!dragging) return;
        dragging = false;
        handle.style.cursor = 'grab';
        if (!track.classList.contains('confirmed')) {
            handle.style.left = '2px';
        }
    }

    handle.addEventListener('mousedown', onDragStart);
    handle.addEventListener('touchstart', onDragStart);
    document.addEventListener('mousemove', onDragMove);
    document.addEventListener('touchmove', onDragMove);
    document.addEventListener('mouseup', onDragEnd);
    document.addEventListener('touchend', onDragEnd);
})();
</script>
@endsection
