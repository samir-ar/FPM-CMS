@extends($layout)

@section('content')
<div class="box box-default">
    <div class="box-header with-border">
        <h3 class="box-title">{{ strip_tags($article->title) }}</h3>
    </div>
    <div class="box-body">
        <p><strong>Total likes:</strong> {{ number_format($likedBy->count()) }}</p>
        <p><strong>Total shares:</strong> {{ number_format($article->shares) }} (running total only — no per-user data available)</p>

        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Member ID</th>
                    <th>Liked At</th>
                </tr>
            </thead>
            <tbody>
                @forelse($likedBy as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->member_id }}</td>
                    <td>{{ $user->liked_at }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="text-center text-muted">No likes yet.</td></tr>
                @endforelse
            </tbody>
        </table>

        <a href="{{ route('admin.content-engagement.index') }}" class="btn btn-default">Back</a>
    </div>
</div>
@endsection
