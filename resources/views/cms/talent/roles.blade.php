@extends($layout)

@section('content')
<section class="content-header">
    <h1>FPM Talent <small>roles members can tick</small></h1>
</section>

<section class="content">
    @if (session('message'))
        <div class="alert alert-success">{{ session('message') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="box box-default">
        <div class="box-body">
            <form method="POST" action="{{ route('admin.talent-roles.update') }}">
                @csrf
                <table class="table table-bordered">
                    <thead><tr><th style="width:80px">Order</th><th>Name</th><th style="width:110px">Shown</th><th style="width:230px">Member writes his own text</th></tr></thead>
                    <tbody>
                    @foreach ($roles as $r)
                        <tr>
                            <td><input type="number" class="form-control" name="roles[{{ $r->id }}][sort_order]" value="{{ $r->sort_order }}"></td>
                            <td><input type="text" class="form-control" dir="rtl" name="roles[{{ $r->id }}][name]" value="{{ $r->name }}" required></td>
                            <td class="text-center"><input type="checkbox" name="roles[{{ $r->id }}][is_active]" value="1" @if ($r->is_active) checked @endif></td>
                            <td class="text-center"><input type="checkbox" name="roles[{{ $r->id }}][allows_text]" value="1" @if ($r->allows_text) checked @endif></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <p class="text-muted">Switch a role off instead of deleting it: old submissions keep what the member ticked. The same list is used by both tracks.</p>
                <button type="submit" class="btn btn-primary">Save the list</button>
            </form>
        </div>
    </div>

    <div class="box box-default">
        <div class="box-header with-border"><h3 class="box-title">Add a role</h3></div>
        <div class="box-body">
            <form method="POST" action="{{ route('admin.talent-roles.store') }}" class="form-inline">
                @csrf
                <input type="text" class="form-control" dir="rtl" name="name" placeholder="Role name" style="width:320px" required>
                <label style="margin:0 10px"><input type="checkbox" name="allows_text" value="1"> member writes his own text</label>
                <button type="submit" class="btn btn-success">Add</button>
            </form>
        </div>
    </div>
</section>
@endsection
