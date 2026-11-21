<!doctype html>
<html>
<body style="font-family:Arial,Helvetica,sans-serif;color:#222;line-height:1.5">
    @if ($isTest)
        <p style="background:#fff3cd;padding:8px 12px;border-radius:6px"><b>This is a test e-mail</b> sent from the Talent Tracks page. No real submission was received.</p>
    @endif
    <h2 style="margin:0 0 10px">FPM Talent: a new form was sent</h2>
    <table cellpadding="6" style="border-collapse:collapse">
        <tr><td><b>Track</b></td><td dir="auto">{{ $track ? $track->title : $submission->track }}</td></tr>
        <tr><td><b>Cycle</b></td><td>{{ $submission->cycle }}</td></tr>
        <tr><td><b>Received</b></td><td>{{ $submission->created_at }}</td></tr>
        <tr><td><b>Sent by</b></td><td dir="auto">{{ $submission->submitter_name }} ({{ $submission->is_guest ? 'guest' : 'member ' . $submission->member_id }})</td></tr>
        @if ($submission->nominee_name)
            <tr><td><b>Named person</b></td><td dir="auto">{{ $submission->nominee_name }}</td></tr>
        @endif
        @php $roles = collect($submission->roles())->pluck('name')->implode(' · '); @endphp
        @if ($roles !== '')
            <tr><td><b>Roles</b></td><td dir="auto">{{ $roles }}</td></tr>
        @endif
    </table>
    <p><a href="{{ $link }}" style="background:#f78e1e;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none">Open the submission in the CMS</a></p>
    <p style="color:#777;font-size:12px">The link asks you to log in to the CMS. The CV and any documents are not attached to this e-mail.</p>
</body>
</html>
