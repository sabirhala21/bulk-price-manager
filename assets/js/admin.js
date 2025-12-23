jQuery(function ($) {

    $('#bpm-preview').on('click', function () {
        $.post(BPM.ajax, {
            action: 'bpm_preview',
            nonce: BPM.nonce,
            ids: $('#product_ids').val()
        }, function (res) {
            $('#bpm-result').html(`
                <p><strong>Total:</strong> ${res.total}</p>
                <p><strong>Simple Products:</strong> ${res.simple}</p>
                <p><strong>Variations:</strong> ${res.variation}</p>
            `);
        });
    });

    $('#bpm-execute').on('click', function () {
        if (!confirm('Are you sure? This will update prices.')) return;

        $.post(BPM.ajax, {
            action: 'bpm_execute',
            nonce: BPM.nonce,
            ids: $('#product_ids').val(),
            action_type: $('#price_action').val(),
            type: $('#price_type').val(),
            value: $('#price_value').val()
        }, function (res) {
            alert(res.data);
        });
    });

});
