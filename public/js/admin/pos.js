(function () {
    const nameInput = document.getElementById('pos-customer-name');
    const phoneInput = document.getElementById('pos-customer-phone');
    const idInput = document.getElementById('pos-customer-id');
    const modeInput = document.getElementById('pos-customer-mode');
    const filterInput = document.getElementById('pos-customer-filter');

    function syncCustomerFields(form) {
        if (!(form instanceof HTMLFormElement) || form.id === 'pos-checkout') {
            return;
        }
        const fields = {
            sync_customer_id: idInput ? idInput.value : '',
            sync_customer_name: nameInput ? nameInput.value : '',
            sync_customer_phone: phoneInput ? phoneInput.value : '',
        };
        Object.entries(fields).forEach(([key, value]) => {
            let input = form.querySelector('input[name="' + key + '"]');
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                form.appendChild(input);
            }
            input.value = value;
        });
    }

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        if (form.id === 'pos-checkout') {
            modeInput.value = idInput && idInput.value ? 'existing' : 'walkin';
            return;
        }
        if (!form.action || form.action.indexOf('/pos') === -1) {
            return;
        }
        syncCustomerFields(form);
    });

    [nameInput, phoneInput].forEach((input) => {
        if (!input) return;
        input.addEventListener('input', function () {
            if (idInput) {
                idInput.value = '';
            }
            if (modeInput) {
                modeInput.value = 'walkin';
            }
        });
    });

    document.querySelectorAll('[data-pick-customer]').forEach((button) => {
        button.addEventListener('click', function () {
            if (idInput) idInput.value = button.getAttribute('data-id') || '';
            if (nameInput) nameInput.value = button.getAttribute('data-name') || '';
            if (phoneInput) phoneInput.value = button.getAttribute('data-phone') || '';
            if (modeInput) modeInput.value = 'existing';
            const modalEl = document.getElementById('posCustomerModal');
            if (modalEl && window.bootstrap) {
                window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            }
        });
    });

    if (filterInput) {
        filterInput.addEventListener('input', function () {
            const q = filterInput.value.toLowerCase().trim();
            document.querySelectorAll('[data-pick-customer]').forEach((row) => {
                const hay = row.getAttribute('data-search') || '';
                row.style.display = hay.indexOf(q) === -1 ? 'none' : '';
            });
        });
    }

    const payOverlay = document.getElementById('pos-pay-overlay');
    const payDialog = payOverlay ? payOverlay.querySelector('.pos-pay-dialog') : null;
    const payCash = payOverlay ? payOverlay.querySelector('.pos-pay-cash') : null;
    const payQr = payOverlay ? payOverlay.querySelector('.pos-pay-qr') : null;
    const openPay = document.getElementById('pos-open-pay');
    const saveBtn = document.getElementById('pos-btn-save');
    const actionInput = document.getElementById('pos-checkout-action');
    const methodInput = document.getElementById('pos-payment-method');
    const checkoutForm = document.getElementById('pos-checkout');
    const tenderedInput = document.getElementById('pos-pay-tendered');
    const changeEl = document.getElementById('pos-pay-change');
    const payTotal = payDialog ? Number(payDialog.getAttribute('data-pay-total') || 0) : 0;
    let tendered = payTotal;
    let tenderedFresh = true;

    function formatVnd(amount) {
        return Math.round(amount).toLocaleString('vi-VN') + ' đ';
    }

    function renderPay() {
        if (tenderedInput) {
            tenderedInput.value = Math.round(tendered).toLocaleString('vi-VN');
        }
        if (!changeEl) {
            return;
        }
        const diff = tendered - payTotal;
        changeEl.classList.toggle('is-short', diff < 0);
        if (diff < 0) {
            changeEl.textContent = formatVnd(Math.abs(diff)) + ' (thiếu)';
        } else {
            changeEl.textContent = formatVnd(diff);
        }
    }

    function setPayMethod(method) {
        const next = method || 'cash';
        document.querySelectorAll('.pos-pay-method[data-pay-method]').forEach((btn) => {
            btn.classList.toggle('is-active', btn.getAttribute('data-pay-method') === next);
        });
        if (methodInput) {
            methodInput.value = next;
        }
        if (payDialog) {
            payDialog.classList.toggle('is-bank', next === 'bank_transfer');
        }
        if (payCash) {
            payCash.hidden = next === 'bank_transfer';
        }
        if (payQr) {
            payQr.hidden = next !== 'bank_transfer';
        }
    }

    function openPayModal() {
        if (!payOverlay || !openPay || openPay.disabled) {
            return;
        }
        tendered = payTotal;
        tenderedFresh = true;
        setPayMethod('cash');
        renderPay();
        payOverlay.hidden = false;
        document.body.classList.add('pos-pay-open');
    }

    function closePayModal() {
        if (!payOverlay) {
            return;
        }
        payOverlay.hidden = true;
        document.body.classList.remove('pos-pay-open');
    }

    if (openPay) {
        openPay.addEventListener('click', openPayModal);
    }
    document.querySelectorAll('[data-pos-pay-close]').forEach((btn) => {
        btn.addEventListener('click', closePayModal);
    });
    if (payOverlay) {
        payOverlay.addEventListener('click', function (event) {
            if (event.target === payOverlay) {
                closePayModal();
            }
        });
    }
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && payOverlay && !payOverlay.hidden) {
            closePayModal();
        }
    });

    document.querySelectorAll('.pos-pay-method[data-pay-method]').forEach((btn) => {
        btn.addEventListener('click', function () {
            setPayMethod(btn.getAttribute('data-pay-method') || 'cash');
        });
    });

    document.querySelectorAll('[data-pay-key]').forEach((btn) => {
        btn.addEventListener('click', function () {
            const key = btn.getAttribute('data-pay-key');
            const digits = String(Math.round(tendered));
            if (key === 'back') {
                tendered = digits.length <= 1 ? 0 : Number(digits.slice(0, -1));
                tenderedFresh = false;
            } else if (tenderedFresh) {
                tendered = key === '000' ? 0 : Number(key);
                tenderedFresh = false;
            } else if (key === '000') {
                tendered = Number((digits === '0' ? '' : digits) + '000');
            } else {
                tendered = Number((digits === '0' ? '' : digits) + key);
            }
            if (!Number.isFinite(tendered) || tendered > 999999999999) {
                tendered = 999999999999;
            }
            renderPay();
        });
    });

    if (tenderedInput) {
        tenderedInput.addEventListener('input', function () {
            const raw = tenderedInput.value.replace(/[^\d]/g, '');
            tendered = raw === '' ? 0 : Number(raw);
            tenderedFresh = false;
            renderPay();
        });
    }

    if (saveBtn && actionInput) {
        saveBtn.addEventListener('click', function () {
            actionInput.value = 'save';
        });
    }

    const confirmPay = document.getElementById('pos-pay-confirm');
    if (confirmPay && checkoutForm && actionInput) {
        confirmPay.addEventListener('click', function () {
            actionInput.value = 'pay';
            checkoutForm.requestSubmit();
        });
    }
})();
