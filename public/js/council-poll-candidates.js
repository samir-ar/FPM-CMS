// National Council poll questions: pick a candidate from the member list (same idea as the Internal Election).
// Loaded after jQuery through the form's 'scripts' list; the search url comes from #candPicker[data-search-url].
$(function () {
    var picker = $('#candPicker');
    if (!picker.length) { return; }
    var searchUrl = picker.data('search-url');

    function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }

    function run() {
        var q = $.trim($('#candSearchInput').val()), box = $('#candResults');
        if (q.length < 2) { box.html('<div style="padding:8px;color:#888;">Type at least 2 characters</div>').show(); return; }
        box.html('<div style="padding:8px;color:#888;">Searching...</div>').show();
        $.get(searchUrl, { q: q }, function (rows) {
            if (!rows.length) { box.html('<div style="padding:8px;color:#888;">No members found</div>'); return; }
            box.empty();
            rows.forEach(function (r) {
                $('<div class="cand-row" style="display:flex;align-items:center;gap:10px;padding:6px 10px;border-bottom:1px solid #eee;cursor:pointer;"></div>')
                    .data('row', r)
                    .html('<img src="' + esc(r.photo) + '" width="40" height="40" style="border-radius:50%;object-fit:cover;background:#dfe5ea;" onerror="this.style.visibility=\'hidden\'">' +
                          '<div><strong dir="auto">' + esc(r.name) + '</strong><br><small class="text-muted">#' + esc(r.member_id) + ' &middot; ' + esc(r.phone) + '</small></div>')
                    .appendTo(box);
            });
        }).fail(function () { box.html('<div style="padding:8px;color:#c00;">Search failed</div>'); });
    }

    function addOption(r) {
        var wrapper = $('.options .options_wrapper').first();
        if (wrapper.find('input[name$="[member_id]"]').filter(function () { return this.value == r.member_id; }).length) {
            alert('This member is already one of the options.'); return;
        }
        var max = -1;
        wrapper.find('input[name^="options["]').each(function () { var m = this.name.match(/^options\[(\d+)\]/); if (m) { max = Math.max(max, +m[1]); } });
        var i = max + 1;
        var row = $('<div class="option-element row"></div>');
        row.append($('<input type="hidden">').attr('name', 'options[' + i + '][member_id]').val(r.member_id));
        row.append($('<div class="form-group col-md-12"></div>').append(
            $('<input class="form-control" type="text" dir="rtl" style="margin-bottom:4px;">').attr('name', 'options[' + i + '][name_ar]').val(r.name),
            $('<small class="text-muted" style="margin-bottom:11px;display:block;"></small>').text('Member ID #' + r.member_id)
        ));
        row.append('<div class="delete-option delete-icon"><i class="fa fa-minus-circle" style="font-size:20px;color:red"></i></div>');
        wrapper.append(row);
    }

    $('#candSearchBtn').on('click', run);
    $('#candSearchInput').on('keydown', function (e) { if (e.which === 13) { e.preventDefault(); run(); } });
    $(document).on('click', '.cand-row', function () {
        addOption($(this).data('row'));
        $('#candResults').hide().empty();
        $('#candSearchInput').val('').focus();
    });
});
