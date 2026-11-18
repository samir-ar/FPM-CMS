// National Council polls list: save the closing date/time of one poll from its row (like the Internal Election's list).
$(function () {
    $('body').on('click', '.save-poll-closes-at-btn', function () {
        var id = $(this).data('id');
        var value = $('.poll-closes-at-input[data-id="' + id + '"]').val();

        $.post('/admin/national-council-poll/' + id + '/closes-at', {
            _token: $('meta[name=csrf-token]').attr('content'),
            closes_at: value
        }).done(function () {
            alert(value ? 'Closing time saved. After it the poll shows as closed in the app.' : 'Closing time removed.');
            if ($.fn.DataTable) { $('table').each(function () { if ($.fn.DataTable.isDataTable(this)) { $(this).DataTable().ajax.reload(null, false); } }); }
        }).fail(function (xhr) {
            console.error('Failed to save closing time', xhr.status, xhr.responseText);
            alert('Failed to save the closing time. See the console for details.');
        });
    });
});
