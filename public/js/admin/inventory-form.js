(function () {
    const stockForm = document.querySelector('[data-inventory-stock-form]');
    if (stockForm) {
        bindModeFields(stockForm, '[name="quantity_on_hand"]', '[name="rental_item_count"]', false);
    }

    const receiptForm = document.querySelector('[data-stock-receipt-form]');
    if (!receiptForm) {
        return;
    }

    const list = document.getElementById('receipt-lines');
    const template = document.getElementById('receipt-line-template');
    const addButton = document.getElementById('add-receipt-line');
    if (!list || !template) {
        return;
    }

    let nextIndex = list.querySelectorAll('.receipt-line').length;

    function nameLine(row, index) {
        row.querySelectorAll('[data-name]').forEach((field) => {
            field.name = 'lines[' + index + '][' + field.getAttribute('data-name') + ']';
        });
        applyLine(row);
    }

    function applyLine(row) {
        const select = row.querySelector('select');
        const sale = row.querySelector('[data-name="sale_quantity"], [name$="[sale_quantity]"]');
        const rental = row.querySelector('[data-name="rental_quantity"], [name$="[rental_quantity]"]');
        if (!select || !sale || !rental) {
            return;
        }
        const mode = select.selectedOptions[0]?.dataset.mode || '';
        sale.disabled = mode === 'rental';
        rental.disabled = mode === 'sale';
        if (sale.disabled) {
            sale.value = '';
        }
        if (rental.disabled) {
            rental.value = '';
        }
    }

    list.querySelectorAll('.receipt-line').forEach((row) => applyLine(row));
    list.addEventListener('change', (event) => {
        const row = event.target.closest('.receipt-line');
        if (row) {
            applyLine(row);
        }
    });
    list.addEventListener('click', (event) => {
        const button = event.target.closest('.remove-receipt-line');
        if (!button) {
            return;
        }
        const rows = list.querySelectorAll('.receipt-line');
        if (rows.length === 1) {
            return;
        }
        button.closest('.receipt-line').remove();
    });
    if (addButton) {
        addButton.addEventListener('click', () => {
            const row = template.content.firstElementChild.cloneNode(true);
            nameLine(row, nextIndex);
            list.appendChild(row);
            nextIndex += 1;
        });
    }

    function bindModeFields(form, saleSelector, rentalSelector, receipt) {
        const select = form.querySelector('[name="product_variant_id"]');
        const sale = form.querySelector(saleSelector);
        const rental = form.querySelector(rentalSelector);
        const hint = form.querySelector('[data-stock-hint]');
        if (!select || !sale || !rental) {
            return;
        }
        function apply() {
            const mode = select.selectedOptions[0]?.dataset.mode || '';
            sale.disabled = mode === 'rental';
            rental.disabled = mode === 'sale';
            if (sale.disabled) {
                sale.value = '';
            }
            if (rental.disabled) {
                rental.value = '';
            }
            if (!hint || receipt) {
                return;
            }
            if (mode === 'rental') {
                hint.textContent = 'SKU chỉ thuê: chỉ số món thuê được lưu. Ô tồn bán không đổi số món.';
            } else if (mode === 'sale') {
                hint.textContent = 'SKU chỉ bán: chỉ tồn bán được lưu. Ô số món thuê không tạo món thuê.';
            } else if (mode === 'both') {
                hint.textContent = 'SKU bán và thuê: ô để trống sẽ không đổi nhóm đó.';
            } else {
                hint.textContent = '';
            }
        }
        select.addEventListener('change', apply);
        apply();
    }
})();
