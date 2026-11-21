@extends($layout)

@section('content')
<section class="content-header">
    <h1>Home card icons <small>the round icon on each Home card</small></h1>
</section>

<section class="content">
    @if (session('message'))
        <div class="alert alert-success">{{ session('message') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.home-card-icons.update') }}" enctype="multipart/form-data">
        @csrf
        <div class="row">
            @foreach ($rows as $type => $r)
                <div class="col-md-4">
                    <div class="box box-default">
                        <div class="box-header with-border"><h3 class="box-title">{{ $r['label'] }}</h3></div>
                        <div class="box-body text-center">
                            <div style="width:92px;height:92px;border-radius:50%;border:2px solid #f78e1e;margin:0 auto 12px;overflow:hidden;background:#fff;display:flex;align-items:center;justify-content:center">
                                @if ($r['row'] && $r['row']->url())
                                    <img src="{{ $r['row']->url() }}" style="width:100%;height:100%;object-fit:cover" alt="">
                                @else
                                    <span class="text-muted" style="font-size:12px">default<br>(FPM logo)</span>
                                @endif
                            </div>
                            <input type="file" name="icon[{{ $type }}]" accept="image/png,image/jpeg,image/webp" class="form-control">
                            <p class="help-block">Square image, e.g. 200 &times; 200 (png / jpg, up to 2 MB).</p>
                            @if ($r['row'] && $r['row']->image)
                                <label style="font-weight:normal"><input type="checkbox" name="remove[{{ $type }}]" value="1"> Remove this icon (back to the default)</label>
                            @endif
                            @if ($type === 'NEWS')
                                <hr>
                                <label style="font-weight:normal;text-align:left">
                                    <input type="checkbox" name="use_for_all_news" value="1" @if ($r['row'] && $r['row']->use_for_all) checked @endif>
                                    Always use this icon for news (ignore each news item's own logo)
                                </label>
                                <p class="help-block" style="text-align:left">Left unticked, a news item that has its own logo keeps it, and this icon is used only for news without one.</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <button type="submit" class="btn btn-primary">Save</button>
    </form>
</section>
@endsection
