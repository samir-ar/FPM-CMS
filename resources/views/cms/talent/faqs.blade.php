@extends($layout)

@section('content')
<section class="content-header">
    <h1>FPM Talent <small>frequently asked questions</small></h1>
</section>

<section class="content">
    @if (session('message'))
        <div class="alert alert-success">{{ session('message') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <ul class="nav nav-tabs" style="margin-bottom:15px">
        <li class="{{ $language === 'ar' ? 'active' : '' }}"><a href="{{ route('admin.talent-faqs.index', ['language' => 'ar']) }}">العربية</a></li>
        <li class="{{ $language === 'en' ? 'active' : '' }}"><a href="{{ route('admin.talent-faqs.index', ['language' => 'en']) }}">English</a></li>
    </ul>

    <div class="callout callout-info">
        In the app the member first picks a language button, then sees the questions in bold; tapping a question shows its answer.
    </div>

    @if ($faqs->isNotEmpty())
    <form method="POST" action="{{ route('admin.talent-faqs.update') }}">
        @csrf
        <input type="hidden" name="language" value="{{ $language }}">
        @foreach ($faqs as $f)
            <div class="box box-default">
                <div class="box-body">
                    <div class="row">
                        <div class="col-sm-1 form-group"><label>Order</label><input type="number" class="form-control" name="faqs[{{ $f->id }}][sort_order]" value="{{ $f->sort_order }}"></div>
                        <div class="col-sm-11 form-group"><label>Question</label><input type="text" class="form-control" dir="{{ $language === 'ar' ? 'rtl' : 'ltr' }}" name="faqs[{{ $f->id }}][question]" value="{{ $f->question }}" required></div>
                    </div>
                    <div class="form-group"><label>Answer</label><textarea class="form-control" rows="4" dir="{{ $language === 'ar' ? 'rtl' : 'ltr' }}" name="faqs[{{ $f->id }}][answer]" required>{{ $f->answer }}</textarea></div>
                    <label style="margin-right:20px"><input type="checkbox" name="faqs[{{ $f->id }}][is_active]" value="1" @if ($f->is_active) checked @endif> Shown in the app</label>
                    <label class="text-danger"><input type="checkbox" name="faqs[{{ $f->id }}][delete]" value="1"> Delete this question when I press Save</label>
                </div>
            </div>
        @endforeach
        <button type="submit" class="btn btn-primary">Save</button>
    </form>
    @else
        <p class="text-muted">No questions in this language yet.</p>
    @endif

    <div class="box box-default" style="margin-top:20px">
        <div class="box-header with-border"><h3 class="box-title">Add a question</h3></div>
        <div class="box-body">
            <form method="POST" action="{{ route('admin.talent-faqs.store') }}">
                @csrf
                <input type="hidden" name="language" value="{{ $language }}">
                <div class="form-group"><label>Question</label><input type="text" class="form-control" dir="{{ $language === 'ar' ? 'rtl' : 'ltr' }}" name="question" required></div>
                <div class="form-group"><label>Answer</label><textarea class="form-control" rows="4" dir="{{ $language === 'ar' ? 'rtl' : 'ltr' }}" name="answer" required></textarea></div>
                <button type="submit" class="btn btn-success">Add</button>
            </form>
        </div>
    </div>
</section>
@endsection
