jQuery(function ($) {

   function bpmToggleActionButtons(enabled) {
        $('#bpm-preview, #bpm-execute').prop('disabled', !enabled);
    }
    bpmToggleActionButtons(false);

    $('#product_category, #product_tag').select2({
        placeholder: 'Select options',
        allowClear: true,
        dropdownParent: $('body'),
        width: '100%'
    });

    $('#product_ids').on('input', function () {
        const hasIds = $(this).val().trim().length > 0;

        $('#product_category, #product_tag')
            .prop('disabled', hasIds)
            .val(null)
            .trigger('change');
    });

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

        // Clear category & tag (Select2-safe)
        $('#product_category, #product_tag')
            .val(null)
            .trigger('change');

        // Clear product table
        $('#bpm-products-table').html('');

        // (Optional) Disable execute button temporarily
        $('#bpm-execute').prop('disabled', false);
        bpmToggleActionButtons(false);
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

    function bpmValidateSelection() {
        const selected = getSelectedProducts();

        if (!selected.length) {
            bpmToast('Please select at least one product or variation', 'error');
            return false;
        }

        return true;
    }

    function bpmValidateExecuteFields() {
        const value = $('#price_value').val().trim();
        const label = $('#operation_label').val().trim();
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
            initBpmDataTable('#bpm-history-content table');
        });
    }

    $('#bpm-view-history').on('click', function () {
        $('#bpm-history-modal').fadeIn(200);
        loadOperationHistory();
    });

    $(document).on('click', '.bpm-close', function () {
        $('#bpm-preview-modal, #bpm-history-modal').fadeOut(200);
    });

    // $('.bpm-close').on('click', function () {
    //     $('#bpm-history-modal').fadeOut(200);
    // });

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

    function initBpmDataTable(selector) {

        if (!$.fn.DataTable) return;

        const $table = $(selector);
        if (!$table.length) return;

        if ($.fn.DataTable.isDataTable($table)) {
            $table.DataTable().destroy();
        }

        $table.DataTable({
            pageLength: 100,
            lengthMenu: [[100, 200, 300, 400, 500, -1], [100, 200, 300, 400, 500, "All"]],
            ordering: false,
            searching: true,
            paging: true,
            info: true,
            language: {
                search: "Search:"
            }
        });
    }

    function initPreviewDataTable() {

        if (!$.fn.DataTable) return;

        if ($.fn.DataTable.isDataTable('#bpm-preview-table')) {
            $('#bpm-preview-table').DataTable().destroy();
        }

        $('#bpm-preview-table').DataTable({
            pageLength: 25,
            lengthMenu: [[25, 50, 100, -1], [25, 50, 100, 'All']],
            ordering: false,
            searching: true,
            lengthChange: true,
            paging: true,
            info: true,
            dom: '<"top"l f>rt<"bottom"ip><"clear">',
            language: {
                search: 'Search preview:'
            }
        });
    }

    $('#bpm-load-products').on('click', function () {

        bpmShowOverlay('Loading products…');

        $.post(BPM.ajax, {
            action: 'bpm_load_products',
            nonce: BPM.nonce,
            ids: $('#product_ids').val(),
            categories: $('#product_category').val(),
            tags: $('#product_tag').val()
        }, function (res) {

            bpmHideOverlay();
            $('#bpm-products-section').show();
            if (!res.length) {
                $('#bpm-products-table').html('<p>No products found.</p>');
                bpmToggleActionButtons(false);
                // $('#bpm-products-section').hide();
                return;
            }
            // $('#bpm-products-section').show();
            let html = `
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="bpm-select-all"></th>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Price</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            res.forEach(p => {
                if (p.type === 'simple') {
                    html += `
                        <tr>
                            <td><input type="checkbox" class="bpm-product"
                                    value="${p.id}"></td>
                            <td><strong>${p.name}</strong></td>
                            <td>Simple</td>
                            <td>${p.price}</td>
                        </tr>
                    `;
                } else {
                    html += `
                        <tr class="bpm-parent">
                            <td></td>
                            <td><strong>${p.name}</strong></td>
                            <td>Variable</td>
                            <td></td>
                        </tr>
                    `;

                    p.children.forEach(v => {
                        html += `
                            <tr class="bpm-variation">
                                <td>
                                    <input type="checkbox"
                                        class="bpm-product"
                                        value="${v.id}">
                                </td>
                                <td>↳ ${v.name}</td>
                                <td>Variation</td>
                                <td>${v.price}</td>
                            </tr>
                        `;
                    });
                }
            });

            html += '</tbody></table>';
            $('#bpm-products-table').html(html);
            initBpmDataTable('#bpm-products-table table');
            bpmToggleActionButtons(true);
        });
    });

    $(document).on('change', '#bpm-select-all', function () {
        $('.bpm-product').prop('checked', this.checked);
    });

    function getSelectedProducts() {
        return $('.bpm-product:checked')
            .map(function () { return $(this).val(); })
            .get();
    }

    function renderTrialPreviewModal(data) {

        let html = `
            <table class="widefat striped" id="bpm-preview-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Current Price</th>
                        <th>New Price</th>
                        <th>Difference</th>
                    </tr>
                </thead>
                <tbody>
        `;

        data.forEach(row => {
            const diffClass = row.diff_raw < 0 ? 'bpm-neg' : 'bpm-pos';
            html += `
                <tr>
                    <td>${row.name}</td>
                    <td>${row.type}</td>
                    <td>${row.old}</td>
                    <td><strong>${row.new}</strong></td>
                    <td class="${diffClass}">${row.diff}</td>
                </tr>
            `;
        });

        html += '</tbody></table>';

        $('#bpm-preview-content').html(html);
        $('#bpm-preview-modal').fadeIn(200);
        initPreviewDataTable();
    }


    $('#bpm-preview').on('click', function () {
        if (!bpmValidateSelection()) return;
        if (!bpmValidateExecuteFields()) return;
        const selected = getSelectedProducts();

        if (!selected.length) {
            bpmToast('Please select at least one product', 'error');
            return;
        }
        bpmShowOverlay('Calculating preview…');
        $.post(BPM.ajax, {
            action: 'bpm_preview',
            nonce: BPM.nonce,
            ids: selected.join(','),
            action_type: $('#price_action').val(),
            type: $('#price_type').val(),
            value: $('#price_value').val()
            // ids: $('#product_ids').val()
        }, function (res) {
            bpmHideOverlay();
            // $('#bpm-result').html(`
            //     <p><strong>Total:</strong> ${res.total}</p>
            //     <p><strong>Simple Products:</strong> ${res.simple}</p>
            //     <p><strong>Variations:</strong> ${res.variation}</p>
            // `);
            if (!res.success) {
                bpmToast(res.data, 'error');
                return;
            }
            renderTrialPreviewModal(res.data);
        });
    });

    $('#bpm-execute').on('click', function () {
        if (!bpmValidateSelection()) return;
        if (!bpmValidateExecuteFields()) return;

        const selected = getSelectedProducts();

        if (!confirm(
            `You are about to update ${selected.length} products.\n\nContinue?`
        )) return;
        bpmShowOverlay('Updating prices, please wait…');
        $('#bpm-execute').prop('disabled', true);
        $.post(BPM.ajax, {
            action: 'bpm_execute',
            nonce: BPM.nonce,
            ids: selected.join(','),
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
