@extends($layout)

@section('content')
<section class="content-header">
    <h1>FPM Talent <small>drop-down lists of the forms</small></h1>
</section>

<section class="content">
    @if (session('message'))
        <div class="alert alert-success">{{ session('message') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="callout callout-info">
        Add, rename, reorder or switch off the values members pick from. Switch a value off instead of deleting it: old submissions keep what they chose.
        The roles members tick are on the <b>Talent Roles</b> page; districts and towns come from the party's district list.
    </div>

    <form method="POST" action="{{ route('admin.talent-choices.update') }}">
        @csrf
        @foreach ($lists as $group => $list)
            <div class="box box-default">
                <div class="box-header with-border"><h3 class="box-title">{{ $list['title'] }}</h3></div>
                <div class="box-body">
                    <table class="table table-bordered">
                        <thead><tr><th style="width:80px">Order</th><th>Value</th><th style="width:110px">Shown</th></tr></thead>
                        <tbody>
                        @foreach ($list['rows'] as $r)
                            <tr>
                                <td><input type="number" class="form-control" name="choices[{{ $r->id }}][sort_order]" value="{{ $r->sort_order }}"></td>
                                <td><input type="text" class="form-control" dir="rtl" name="choices[{{ $r->id }}][name]" value="{{ $r->name }}" required></td>
                                <td class="text-center"><input type="checkbox" name="choices[{{ $r->id }}][is_active]" value="1" @if ($r->is_active) checked @endif></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
        <button type="submit" class="btn btn-primary">Save all lists</button>
    </form>

    <div class="box box-default" style="margin-top:20px">
        <div class="box-header with-border"><h3 class="box-title">Add a value</h3></div>
        <div class="box-body">
            <form method="POST" action="{{ route('admin.talent-choices.store') }}" class="form-inline">
                @csrf
                <select name="group" class="form-control">
                    @foreach ($lists as $group => $list)
                        <option value="{{ $group }}">{{ $list['title'] }}</option>
                    @endforeach
                </select>
                <input type="text" name="name" class="form-control" dir="rtl" placeholder="New value" style="width:280px" required>
                <button type="submit" class="btn btn-success">Add</button>
            </form>
        </div>
    </div>
</section>
@endsection
