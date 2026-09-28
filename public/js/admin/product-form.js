document.addEventListener('DOMContentLoaded', () => {
    const list = document.getElementById('variant-list');
    const template = document.getElementById('variant-template');
    const modeSelect = document.getElementById('offer_mode');
    if (!list || !template || !modeSelect) {
        return;
    }
    let nextIndex = Number(list.getAttribute('data-next-index') || 0);

    const syncQtyFields = () => {
        const mode = modeSelect.value;
        document.querySelectorAll('.js-qty-sale').forEach((el) => {
            el.classList.remove('d-none');
            el.querySelectorAll('input').forEach((input) => { input.disabled = false; });
            const label = el.querySelector('.js-label-sale-qty');
            if (label) {
                label.textContent = mode === 'rental' ? 'Món thuê *' : 'Tồn bán *';
            }
        });
        document.querySelectorAll('.js-qty-rental').forEach((el) => {
            el.classList.toggle('d-none', mode !== 'both');
            el.querySelectorAll('input').forEach((input) => { input.disabled = mode !== 'both'; });
        });
    };

    const addBtn = document.getElementById('add-variant');
    if (addBtn) {
        addBtn.addEventListener('click', () => {
            const row = template.content.firstElementChild.cloneNode(true);
            row.querySelectorAll('[data-field]').forEach((input) => {
                input.name = `variants[${nextIndex}][${input.dataset.field}]`;
                input.removeAttribute('data-field');
            });
            list.appendChild(row);
            nextIndex++;
            syncQtyFields();
        });
    }
    list.addEventListener('click', (event) => {
        const button = event.target.closest('.remove-variant');
        if (!button) return;
        if (list.querySelectorAll('.variant-row').length === 1) {
            window.alert('Sản phẩm phải có ít nhất một phân loại.');
            return;
        }
        button.closest('.variant-row').remove();
    });
    modeSelect.addEventListener('change', syncQtyFields);
    syncQtyFields();
});
