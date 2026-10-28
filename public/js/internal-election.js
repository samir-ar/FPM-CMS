$(document).ready(function(){
    $('body').on('click', '.publish', function() {
        $ele =  $(this);
        $ele.toggleClass('btn-success');


        $.ajax({
            url: "/admin/internal-election/publish/"+$ele.attr('id'),
            data: JSON.stringify({
                '_token':  $('meta[name=csrf-token]').attr('content')
             }),
             method: 'POST'
          });
    });

    // Election list: save the closing date/time, per election row.
    $('body').on('click', '.save-closes-at-row-btn', function(){
        var id = $(this).data('id');
        var value = $('.election-closes-at-input[data-id="' + id + '"]').val();

        $.post('/admin/internal-election/update-closes-at/' + id, {
            _token: $('meta[name=csrf-token]').attr('content'),
            closes_at: value
        }).done(function(){
            alert('Closing time saved.');
        }).fail(function(xhr){
            console.error('Failed to save closing time', xhr.status, xhr.responseText);
            alert('Failed to save closing time — see console for details.');
        });
    });

    // Edit Election page: add a single permitted voter by Member ID without
    // needing a full Excel re-upload (which replaces the whole list).
    // Two-step: Search shows the real name first, then a separate Add
    // button (shown only if they aren't already permitted) confirms it.
    $('body').on('click', '#addSingleVoterSearchBtn', function(){
        var $btn = $(this);
        var electionId = $btn.data('election-id');
        var memberId = $('#addSingleVoterMemberId').val().trim();
        var $result = $('#addSingleVoterResult');

        if (!memberId) {
            $result.html('<span class="text-danger">Enter a Member ID first.</span>');
            return;
        }

        $btn.prop('disabled', true);
        $result.html('<span class="text-muted">Looking up...</span>');

        $.post('/admin/internal-election/' + electionId + '/permitted-voters/lookup', {
            _token: $('meta[name=csrf-token]').attr('content'),
            member_id: memberId
        }).done(function(response){
            if (response.already_permitted) {
                $result.html('<span class="text-success">Already in the permitted list: ' + response.name + ' (' + response.member_id + ')</span>');
            } else {
                var $confirm = $('<span>')
                    .addClass('text-muted')
                    .html('Found: <strong>' + response.name + '</strong> (' + response.member_id + ') — not yet permitted. ');
                var $addBtn = $('<button type="button" class="btn btn-primary btn-sm">Add</button>')
                    .on('click', function(){
                        $addBtn.prop('disabled', true).text('Adding...');
                        $.post('/admin/internal-election/' + electionId + '/permitted-voters/add-one', {
                            _token: $('meta[name=csrf-token]').attr('content'),
                            member_id: response.member_id
                        }).done(function(addResponse){
                            $result.html('<span class="text-success">Added: ' + addResponse.name + ' (' + addResponse.member_id + ')</span>');
                            $('#addSingleVoterMemberId').val('');
                        }).fail(function(xhr){
                            var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Failed to add voter — see console for details.';
                            $result.html('<span class="text-danger">' + msg + '</span>');
                            console.error('Add single voter failed', xhr.status, xhr.responseText);
                        });
                    });
                $result.empty().append($confirm).append($addBtn);
            }
        }).fail(function(xhr){
            var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Lookup failed — see console for details.';
            $result.html('<span class="text-danger">' + msg + '</span>');
            console.error('Lookup single voter failed', xhr.status, xhr.responseText);
        }).always(function(){
            $btn.prop('disabled', false);
        });
    });

    // Permitted-voters list: search box navigates via a plain GET-style
    // redirect (not a real <form>, since this list is embedded inside the
    // page's outer edit-form and a nested form would be invalid HTML).
    $('body').on('click', '.permitted-search-btn', function(){
        var baseUrl = $(this).data('url');
        var term = $('#permittedSearchInput').val().trim();
        var separator = baseUrl.indexOf('?') === -1 ? '?' : '&';
        window.location.href = baseUrl + (term ? separator + 'permitted_search=' + encodeURIComponent(term) : '') + '#permitted-voters';
    });
    $('body').on('keydown', '#permittedSearchInput', function(e){
        if (e.key === 'Enter' || e.keyCode === 13) {
            e.preventDefault();
            $('.permitted-search-btn').trigger('click');
        }
    });

    // Permitted-voters list: remove one member without a full Excel
    // re-upload (which replaces everyone, see update()'s delete-then-import
    // behavior).
    $('body').on('click', '.remove-permitted-voter-link', function(e){
        e.preventDefault();
        var $link = $(this);
        var electionId = $link.data('election-id');
        var permissionId = $link.data('permission-id');

        if (!confirm('Remove this member from the permitted-voters list?')) {
            return;
        }

        $.ajax({
            url: '/admin/internal-election/' + electionId + '/permitted-voters/' + permissionId,
            method: 'DELETE',
            data: { _token: $('meta[name=csrf-token]').attr('content') }
        }).done(function(){
            $link.closest('tr').fadeOut(200, function(){ $(this).remove(); });
        }).fail(function(xhr){
            alert('Failed to remove — see console for details.');
            console.error('Remove permitted voter failed', xhr.status, xhr.responseText);
        });
    });

    // Candidate list: inline-editable display order, saved on change.
    $('body').on('change', '.candidate-order-input', function(){
        var $input = $(this);
        var id = $input.data('id');
        var value = $input.val();

        $.post('/admin/internal-election-cadidates-update-order/' + id, {
            _token: $('meta[name=csrf-token]').attr('content'),
            display_order: value
        }).fail(function(xhr){
            console.error('Failed to update order', xhr.status, xhr.responseText);
            alert('Failed to save order — see console for details.');
        });
    });

    // Candidate form: toggle between searching an existing FPM member and
    // entering a candidate's name manually.
    if ($('#fpm-search-input').length) {
        function toggleCandidateSource() {
            var isFpm = $('#source-fpm').is(':checked');
            $('#fpm-search-block').toggle(isFpm);
            $('#manual-image-block').toggle(!isFpm);
            if (!isFpm) {
                $('#member_id_input').val('');
                $('#fpm-search-results').hide().empty();
                $('#fpm-selected-preview').empty();
            }
        }

        $('input[name=source]').on('change', toggleCandidateSource);
        toggleCandidateSource();

        function runFpmSearch() {
            var q = $('#fpm-search-input').val().trim();
            var stateIds = $('select[name="state_ids[]"]').val() || [];

            if (!stateIds.length) {
                $('#fpm-search-results').empty().append('<div style="padding:8px;color:#c00;">Select an Election State above first — search is limited to members from those states.</div>').show();
                return;
            }

            if (q.length < 2) {
                $('#fpm-search-results').empty().append('<div style="padding:8px;color:#888;">Type at least 2 characters</div>').show();
                return;
            }

            $('#fpm-search-results').empty().append('<div style="padding:8px;color:#888;">Searching...</div>').show();

            $.get('/admin/internal-election-cadidates-search-fpm', { q: q, state_ids: stateIds })
                .done(function(results){
                    var $list = $('#fpm-search-results').empty();

                    if (!results.length) {
                        $list.append('<div style="padding:8px;color:#888;">No matches</div>');
                    } else {
                        results.forEach(function(r){
                            var $row = $('<div>')
                                .css({padding: '8px', cursor: 'pointer', borderBottom: '1px solid #eee'})
                                .html('<b>' + r.name + '</b> — ' + r.member_id + ' — ' + (r.mobile || ''))
                                .on('mouseenter', function(){ $(this).css('background', '#f5f5f5'); })
                                .on('mouseleave', function(){ $(this).css('background', ''); })
                                .on('click', function(){ selectFpmCandidate(r); });
                            $list.append($row);
                        });
                    }

                    $list.show();
                })
                .fail(function(xhr){
                    console.error('FPM member search failed', xhr.status, xhr.responseText);
                    $('#fpm-search-results').empty()
                        .append('<div style="padding:8px;color:#c00;">Search failed (' + xhr.status + '). Check console for details.</div>')
                        .show();
                });
        }

        $('#fpm-search-btn').on('click', runFpmSearch);
        $('#fpm-search-input').on('keydown', function(e){
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                runFpmSearch();
            }
        });

        function selectFpmCandidate(r) {
            $('#member_id_input').val(r.member_id);

            var parts = (r.name || '').trim().split(/\s+/);
            var first = parts[0] || '';
            var family = parts.length > 1 ? parts[parts.length - 1] : '';
            var father = parts.length > 2 ? parts.slice(1, -1).join(' ') : '';

            $('#first_name_input').val(first);
            $('#father_name_input').val(father);
            $('#family_name_input').val(family);

            $('#fpm-selected-preview').html(
                '<img src="' + r.photo + '" width="50" height="50" style="border-radius:50%;object-fit:cover;margin-left:8px;">' +
                '<b>' + r.name + '</b> (' + r.member_id + ')'
            );

            $('#fpm-search-results').hide().empty();
            $('#fpm-search-input').val(r.name);
        }
    }
});