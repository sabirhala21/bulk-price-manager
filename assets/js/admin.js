jQuery(function ($) {

    function bpmResetForm() {
        // Reset inputs
        $('#product_ids').val('');
        $('#price_value').val('');
        $('#operation_label').val('');

        // Reset selects to first option
        $('#price_action').prop('selectedIndex', 0);
        $('#price_type').prop('selectedIndex', 0);

        // Clear results / preview
        $('#bpm-result').html('');

        // (Optional) Disable execute button temporarily
        $('#bpm-execute').prop('disabled', false);
    }

    function bpmToast(message, type = 'success') {
        const bg = type === 'error' ? '#e74c3c' : '#2ecc71';

        $('#bpm-toast')
            .css('background', bg)
            .text(message)
            .fadeIn(200)
            .delay(3000)
            .fadeOut(400);
    }

    function bpmShowOverlay(text = 'Processing, please wait…') {
        $('#bpm-overlay-text').text(text);
        $('#bpm-overlay').fadeIn(150);
    }

    function bpmHideOverlay() {
        $('#bpm-overlay').fadeOut(150);
    }


    function bpmValidateExecute() {
        const ids = $('#product_ids').val().trim();
        const value = $('#price_value').val().trim();
        const label = $('#operation_label').val().trim();

        if (!ids) {
            bpmToast('Please enter at least one Product ID', 'error');
            return false;
        }

        if (!value || isNaN(value)) {
            bpmToast('Please enter a valid price value', 'error');
            return false;
        }

        if (!label) {
            bpmToast('Operation label is required', 'error');
            return false;
        }

        return true;
    }


    // function loadRollbackHistory() {
    //     $.post(BPM.ajax, {
    //         action: 'bpm_history',
    //         nonce: BPM.nonce
    //     }, function (res) {
    //         if (!res || !res.length) {
    //             $('#bpm-history').html('<p>No operations found.</p>');
    //             return;
    //         }
    //         let html = '<ul>';
    //         res.forEach(r => {
    //             html += `<li>
    //                 <strong>${r.operation_label}</strong>
    //                 <br>
    //                 ${r.date} – ${r.total} items
    //                 <br>
    //                 <button data-op="${r.operation_id}" class="rollback">Rollback</button>
    //             </li><hr>`;
    //         });
    //         html += '</ul>';
    //         $('#bpm-history').html(html);
    //     });
    // }

    function loadOperationHistory() {
        $('#bpm-history-content').html('<p>Loading history…</p>');

        $.post(BPM.ajax, {
            action: 'bpm_history',
            nonce: BPM.nonce
        }, function (res) {

            if (!res.length) {
                $('#bpm-history-content').html('<p>No operations found.</p>');
                return;
            }

            let html = `
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Label</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            res.forEach(r => {
                const disabled = r.rolled_back == 1 ? 'disabled' : '';
                const badge = r.rolled_back == 1
                    ? '<span style="color:#e74c3c;font-weight:bold;">Rolled Back</span>'
                    : '<span style="color:#2ecc71;font-weight:bold;">Active</span>';

                html += `
                    <tr style="${r.rolled_back == 1 ? 'opacity:0.6;' : ''}">
                        <td><strong>${r.operation_label}</strong><br>${badge}</td>
                        <td>${r.performed_at}</td>
                        <td>${r.total_items}</td>
                        <td>
                            <button class="button rollback-btn"
                                    data-op="${r.operation_id}"
                                    ${disabled}>
                                Rollback
                            </button>
                        </td>
                    </tr>
                `;
            });

            html += '</tbody></table>';

            $('#bpm-history-content').html(html);
        });
    }

    $('#bpm-view-history').on('click', function () {
        $('#bpm-history-modal').fadeIn(200);
        loadOperationHistory();
    });

    $('.bpm-close').on('click', function () {
        $('#bpm-history-modal').fadeOut(200);
    });

    function executeRollback(opId) {
        bpmShowOverlay('Rolling back prices…');
        $.post(BPM.ajax, {
            action: 'bpm_rollback',
            nonce: BPM.nonce,
            operation_id: opId
        }, function (res) {
            bpmHideOverlay();
            if (res.success) {
                bpmToast(res.data);
                loadOperationHistory();
            } else {
                bpmToast(res.data, 'error');
            }
        });
    }


    $(document).on('click', '.rollback-btn', function () {
        const opId = $(this).data('op');

        $.post(BPM.ajax, {
            action: 'bpm_operation_products',
            nonce: BPM.nonce,
            operation_id: opId
        }, function (products) {

            let html = '<ul>';
            products.forEach(p => {
                html += `<li>${p.name} (${p.new} → ${p.old})</li>`;
            });
            html += '</ul>';

            if (!confirm(
                `This will rollback ${products.length} products:\n\n` +
                products.map(p => p.name).join('\n')
            )) return;

            // Proceed with rollback
            executeRollback(opId);
        });
    });



    $('#bpm-preview').on('click', function () {
        if (!bpmValidateExecute()) return;
        bpmShowOverlay('Updating prices, please wait…');
        $.post(BPM.ajax, {
            action: 'bpm_preview',
            nonce: BPM.nonce,
            ids: $('#product_ids').val()
        }, function (res) {
            bpmHideOverlay();
            $('#bpm-result').html(`
                <p><strong>Total:</strong> ${res.total}</p>
                <p><strong>Simple Products:</strong> ${res.simple}</p>
                <p><strong>Variations:</strong> ${res.variation}</p>
            `);
        });
    });

    $('#bpm-execute').on('click', function () {
        if (!bpmValidateExecute()) return;
        if (!confirm('Are you sure? This will update prices.')) return;
        bpmShowOverlay('Updating prices, please wait…');
        $('#bpm-execute').prop('disabled', true);
        $.post(BPM.ajax, {
            action: 'bpm_execute',
            nonce: BPM.nonce,
            ids: $('#product_ids').val(),
            operation_label: $('#operation_label').val(),
            action_type: $('#price_action').val(),
            type: $('#price_type').val(),
            value: $('#price_value').val()
        }, function (res) {
            bpmHideOverlay();
            if (res.success) {
                // alert(res.data);
                $('#bpm-execute').prop('disabled', false);
                bpmToast(res.data.message);
                $('#bpm-result').html(
                    `<p><strong>Last Operation ID:</strong> ${res.data.operation_id}</p>`
                );
                // loadRollbackHistory();
                setTimeout(() => {
                    bpmResetForm();
                }, 3000);
            } else {
                bpmToast(res.data, 'error');
                $('#bpm-execute').prop('disabled', false);
            }
        });
    });

});
