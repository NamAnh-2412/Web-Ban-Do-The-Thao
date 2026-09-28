document.querySelectorAll('[data-return-item]').forEach(function (box) {
    const select = box.querySelector('[data-return-condition]');
    const extra = box.querySelector('.return-compensation');
    const fee = box.querySelector('[data-return-fee]');
    const deposit = Number(box.getAttribute('data-deposit') || 0);
    function sync() {
        const bad = select && (select.value === 'damaged' || select.value === 'lost');
        if (extra) extra.hidden = !bad;
        if (fee && select && select.value === 'lost' && (fee.value === '' || fee.value === '0')) {
            fee.placeholder = String(Math.round(deposit));
        }
    }
    if (select) select.addEventListener('change', sync);
    sync();
});
