jQuery(function ($) {
    var frame = document.getElementById('hs-email-preview-frame');

    var previewTemplates = {
        app_received: { subject: 'email_app_received_subject', body: 'email_app_received_body' },
        app_received_admin: { subject: 'email_app_received_admin_subject', body: 'email_app_received_admin_body' },
        status_changed: { subject: 'email_status_changed_subject', body: 'email_status_changed_body' },
        offer: { subject: 'email_offer_subject', body: 'email_offer_body' },
        rejected: { subject: 'email_rejected_subject', body: 'email_rejected_body' },
        interview: { subject: 'email_interview_subject', body: 'email_interview_body' }
    };

    var previewTimer;

    function refreshEmailPreview() {
        if (typeof hs_ajax === 'undefined') return;

        var tpl = $('#hs-preview-template').val();
        var map = previewTemplates[tpl];
        if (!map) return;

        var data = $('#hs-settings-form').serializeArray();
        data.push({ name: 'action', value: 'hs_preview_email' });
        data.push({ name: 'nonce', value: hs_ajax.nonce });
        data.push({ name: 'preview_subject', value: $('input[name="' + map.subject + '"]').val() || '' });
        data.push({ name: 'preview_body', value: $('textarea[name="' + map.body + '"]').val() || '' });

        $('#hs-preview-subject').css('opacity', '0.5');
        $.post(hs_ajax.ajax_url, $.param(data))
            .done(function (resp) {
                if (resp.success) {
                    $('#hs-preview-subject').text(resp.data.subject).css('opacity', '1');
                    if (frame) frame.srcdoc = resp.data.html;
                }
            })
            .fail(function () {
                $('#hs-preview-subject').css('opacity', '1');
            });
    }

    function schedulePreview() {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(refreshEmailPreview, 500);
    }

    var mediaFrame;
    $('#hs-email-logo-upload').on('click', function (e) {
        e.preventDefault();

        if (typeof wp === 'undefined' || !wp.media) {
            window.alert('Media library is not available. Please reload the page.');
            return;
        }

        if (mediaFrame) {
            mediaFrame.open();
            return;
        }

        mediaFrame = wp.media({
            title: hs_settings.i18n.select_logo,
            button: { text: hs_settings.i18n.use_logo },
            library: { type: 'image' },
            multiple: false
        });

        mediaFrame.on('select', function () {
            var attachment = mediaFrame.state().get('selection').first().toJSON();
            $('#hs-email-logo-id').val(attachment.id);
            $('#hs-email-logo-preview').html(
                '<img src="' + attachment.url + '" alt="" style="max-width:180px;max-height:60px;object-fit:contain;display:block;">'
            );
            $('#hs-email-logo-remove').show();
            schedulePreview();
        });

        mediaFrame.open();
    });

    $('#hs-email-logo-remove').on('click', function (e) {
        e.preventDefault();
        $('#hs-email-logo-id').val('');
        $('#hs-email-logo-preview').empty();
        $(this).hide();
        schedulePreview();
    });

    $('#hs-preview-template').on('change', refreshEmailPreview);
    $('#hs-settings-form').on('input change', 'input, textarea, select', schedulePreview);
});
