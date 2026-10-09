(function () {
    const boot = document.getElementById('product-page-config');
    if (!boot) {
        return;
    }

    const cfg = JSON.parse(boot.textContent);
    const API_BASE = cfg.apiBase;
    const PRODUCT = cfg.product;
    const canSale = cfg.canSale;
    const canRent = cfg.canRent;
    const picker = document.getElementById('variant-picker');
    const hasAttrs = picker?.dataset.hasAttrs === '1';

    const salePrice = document.getElementById('sale-price');
    const saleAvailability = document.getElementById('sale-availability');
    const rentPrice = document.getElementById('rent-price');
    const rentDeposit = document.getElementById('rent-deposit');
    const rentalAvailability = document.getElementById('rental-availability');
    const rentalQuote = document.getElementById('rental-quote');
    const startDate = document.getElementById('start-date');
    const endDate = document.getElementById('end-date');
    const panelSale = document.getElementById('panel-sale');
    const panelRental = document.getElementById('panel-rental');
    const cartQty = document.getElementById('cart-qty');
    const cartQtyLabel = document.getElementById('cart-qty-label');
    const cartQtyHint = document.getElementById('cart-qty-hint');
    const addBtn = document.getElementById('add-cart-btn');
    const skuLabel = document.getElementById('variant-sku-label');
    const cartVariantInput = document.getElementById('cart-variant-id');

    let selectedColor = null;
    let selectedSize = null;
    let selectedSkuId = null;
    let rentalFree = 0;
    let saleFree = 0;
    let lastQuote = null;

    function money(value) {
        if (value === null || value === undefined) return '—';
        return new Intl.NumberFormat('vi-VN').format(value) + 'đ';
    }

    function normalize(value) {
        if (value === null || value === undefined || value === '') {
            return null;
        }
        return String(value);
    }

    function chipButtons(attr) {
        return Array.from(document.querySelectorAll('.variant-chip[data-attr="' + attr + '"]'));
    }

    function setLabel(attr, value) {
        const el = document.querySelector('[data-variant-label="' + attr + '"]');
        if (el) {
            el.textContent = value || '—';
        }
    }

    function findVariant(color, size) {
        return PRODUCT.variants.find((row) => {
            const rowColor = normalize(row.color);
            const rowSize = normalize(row.size);
            if (color !== null && rowColor !== color) return false;
            if (size !== null && rowSize !== size) return false;
            return true;
        }) || null;
    }

    function currentVariant() {
        if (!hasAttrs) {
            const id = Number(selectedSkuId || cartVariantInput?.value || PRODUCT.variants[0]?.id);
            return PRODUCT.variants.find((row) => row.id === id) || PRODUCT.variants[0] || null;
        }
        return findVariant(selectedColor, selectedSize);
    }

    function syncChipState() {
        if (!hasAttrs) {
            chipButtons('sku').forEach((btn) => {
                const active = Number(btn.dataset.value) === Number(selectedSkuId);
                btn.classList.toggle('is-active', active);
                btn.setAttribute('aria-pressed', active ? 'true' : 'false');
                btn.disabled = false;
            });
            setLabel('sku', currentVariant()?.sku || '—');
            return;
        }

        const colors = chipButtons('color');
        const sizes = chipButtons('size');

        colors.forEach((btn) => {
            const color = normalize(btn.dataset.value);
            const available = PRODUCT.variants.some((row) => {
                if (normalize(row.color) !== color) return false;
                if (selectedSize !== null && normalize(row.size) !== selectedSize) return false;
                return true;
            });
            const active = selectedColor !== null && color === selectedColor;
            btn.disabled = !available;
            btn.classList.toggle('is-active', active);
            btn.classList.toggle('is-disabled', !available);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        sizes.forEach((btn) => {
            const size = normalize(btn.dataset.value);
            const available = PRODUCT.variants.some((row) => {
                if (normalize(row.size) !== size) return false;
                if (selectedColor !== null && normalize(row.color) !== selectedColor) return false;
                return true;
            });
            const active = selectedSize !== null && size === selectedSize;
            btn.disabled = !available;
            btn.classList.toggle('is-active', active);
            btn.classList.toggle('is-disabled', !available);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        setLabel('color', selectedColor);
        setLabel('size', selectedSize);
    }

    function applyVariantSelection() {
        const variant = currentVariant();
        if (cartVariantInput) {
            cartVariantInput.value = variant ? String(variant.id) : '';
        }
        if (skuLabel) {
            skuLabel.textContent = variant
                ? ('SKU: ' + variant.sku)
                : 'Chọn đủ phân loại để tiếp tục.';
        }
        syncChipState();
        refresh();
    }

    function pickInitialVariant() {
        const first = PRODUCT.variants[0];
        if (!first) {
            return;
        }
        if (!hasAttrs) {
            selectedSkuId = first.id;
            applyVariantSelection();
            return;
        }
        selectedColor = normalize(first.color);
        selectedSize = normalize(first.size);
        applyVariantSelection();
    }

    function setMode(mode) {
        document.querySelectorAll('.mode-toggle [data-mode]').forEach((btn) => {
            btn.classList.toggle('active', btn.dataset.mode === mode);
        });
        if (panelSale) panelSale.classList.toggle('d-none', mode !== 'sale');
        if (panelRental) panelRental.classList.toggle('d-none', mode !== 'rental');
        const lineType = document.getElementById('cart-line-type');
        const qtyWrap = document.getElementById('qty-wrap');
        if (lineType) lineType.value = mode;
        if (qtyWrap) qtyWrap.classList.remove('d-none');
        if (cartQtyLabel) cartQtyLabel.textContent = mode === 'sale' ? 'Số lượng mua' : 'Số lượng thuê';
        if (cartQtyHint) cartQtyHint.textContent = mode === 'rental' ? 'Mỗi món là một lịch thuê riêng.' : '';
        if (addBtn) addBtn.textContent = mode === 'sale' ? 'Thêm mua' : 'Thêm thuê';
        if (cartQty && mode === 'sale') {
            cartQty.removeAttribute('max');
        }
        refresh();
    }

    function currentMode() {
        return document.getElementById('cart-line-type')?.value || cfg.defaultMode;
    }

    function updateAddButton() {
        if (!addBtn) return;
        const mode = currentMode();
        const variant = currentVariant();
        const qty = Number(cartQty?.value) || 1;
        addBtn.textContent = mode === 'sale' ? 'Thêm mua' : 'Thêm thuê';
        let ok = !!variant;
        if (mode === 'sale') {
            ok = ok && !!variant.sale_price && saleFree > 0 && qty <= saleFree;
        } else {
            ok = ok
                && !!variant.rental_price_per_day
                && !!(startDate?.value && endDate?.value)
                && rentalFree > 0
                && qty <= rentalFree;
        }
        addBtn.disabled = !ok;
    }

    async function loadSale() {
        const variant = currentVariant();
        if (!variant) {
            salePrice.textContent = '—';
            saleAvailability.textContent = 'Chọn màu và size còn hàng.';
            saleFree = 0;
            updateAddButton();
            return;
        }
        salePrice.textContent = variant.sale_price ? ('Giá bán ' + money(variant.sale_price)) : 'Không bán biến thể này';
        const url = API_BASE + '/kho/ban?product_variant_id=' + variant.id;
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        const json = await res.json();
        const qty = json.quantity_available ?? 0;
        saleFree = variant.sale_price ? qty : 0;
        saleAvailability.textContent = !variant.sale_price
            ? 'Không bán biến thể này.'
            : (qty > 0 ? ('Còn ' + qty + ' sản phẩm.') : 'Hết hàng bán.');
        if (cartQty && currentMode() === 'sale') {
            if (saleFree > 0) {
                cartQty.max = String(saleFree);
                if (Number(cartQty.value) > saleFree) cartQty.value = String(saleFree);
            } else {
                cartQty.removeAttribute('max');
            }
        }
        updateAddButton();
    }

    async function loadRental() {
        const variant = currentVariant();
        if (!variant) {
            rentPrice.textContent = '—';
            rentDeposit.textContent = '';
            rentalAvailability.textContent = 'Chọn màu và size còn hàng.';
            rentalFree = 0;
            lastQuote = null;
            if (rentalQuote) rentalQuote.textContent = '';
            updateAddButton();
            return;
        }
        rentPrice.textContent = variant.rental_price_per_day
            ? ('Thuê ' + money(variant.rental_price_per_day) + '/ngày')
            : 'Không thuê biến thể này';
        rentDeposit.textContent = variant.deposit_amount
            ? ('Cọc ' + money(variant.deposit_amount) + ' — xem chính sách cọc.')
            : '';

        if (!startDate.value || !endDate.value) {
            rentalAvailability.textContent = 'Chọn ngày để xem món thuê còn trống.';
            if (rentalQuote) rentalQuote.textContent = '';
            rentalFree = 0;
            lastQuote = null;
            if (cartQty) cartQty.removeAttribute('max');
            updateAddButton();
            return;
        }

        const url = API_BASE + '/kho/thue?product_variant_id=' + variant.id
            + '&start_date=' + startDate.value + '&end_date=' + endDate.value;
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        const json = await res.json();
        const qty = json.quantity_available ?? 0;
        rentalFree = qty;
        rentalAvailability.textContent = qty > 0
            ? ('Còn ' + qty + ' món trống trong khoảng ngày này.')
            : 'Không còn món thuê trống. Sản phẩm thuê cần có món (asset) ở kho, không chỉ số tồn bán.';
        if (cartQty) {
            if (qty > 0) {
                cartQty.max = String(qty);
                if (Number(cartQty.value) > qty) cartQty.value = String(qty);
            } else {
                cartQty.max = '1';
            }
        }

        if (!variant.rental_price_per_day) {
            lastQuote = null;
            rentalFree = 0;
            if (rentalQuote) rentalQuote.textContent = '';
            updateAddButton();
            return;
        }
        const quoteUrl = API_BASE + '/thue/bao-gia?start_date=' + startDate.value
            + '&end_date=' + endDate.value
            + '&daily_rate=' + variant.rental_price_per_day
            + (variant.rental_price_per_week ? ('&weekly_rate=' + variant.rental_price_per_week) : '')
            + '&deposit_amount=' + (variant.deposit_amount || 0);
        const quoteRes = await fetch(quoteUrl, { headers: { Accept: 'application/json' } });
        lastQuote = await quoteRes.json();
        renderRentalQuote();
        updateAddButton();
    }

    function renderRentalQuote() {
        if (!rentalQuote) return;
        const d = lastQuote;
        if (!d || !d.days) {
            rentalQuote.textContent = '';
            return;
        }
        const n = Math.max(1, Number(cartQty?.value) || 1);
        rentalQuote.textContent = n + ' món × ' + d.days + ' ngày: thuê ' + money(d.rental_amount * n)
            + ' + cọc ' + money(d.deposit_amount * n)
            + ' = ' + money(d.payable_now * n);
    }

    function refresh() {
        const saleOn = panelSale && !panelSale.classList.contains('d-none');
        if (saleOn && canSale) loadSale();
        if ((!saleOn || !canSale) && canRent) loadRental();
    }

    document.querySelectorAll('.mode-toggle [data-mode]').forEach((btn) => {
        btn.addEventListener('click', () => setMode(btn.dataset.mode));
    });

    picker?.addEventListener('click', (event) => {
        const btn = event.target.closest('.variant-chip');
        if (!btn || btn.disabled) {
            return;
        }
        const attr = btn.dataset.attr;
        const value = normalize(btn.dataset.value);

        if (attr === 'color') {
            selectedColor = value;
            if (selectedSize && !findVariant(selectedColor, selectedSize)) {
                selectedSize = null;
                const firstSize = PRODUCT.variants.find((row) => normalize(row.color) === selectedColor);
                selectedSize = normalize(firstSize?.size);
            }
        } else if (attr === 'size') {
            selectedSize = value;
            if (selectedColor && !findVariant(selectedColor, selectedSize)) {
                selectedColor = null;
                const firstColor = PRODUCT.variants.find((row) => normalize(row.size) === selectedSize);
                selectedColor = normalize(firstColor?.color);
            }
        } else if (attr === 'sku') {
            selectedSkuId = Number(btn.dataset.value);
        }

        applyVariantSelection();
    });

    startDate?.addEventListener('change', loadRental);
    endDate?.addEventListener('change', loadRental);
    cartQty?.addEventListener('input', function () {
        const lineType = document.getElementById('cart-line-type')?.value;
        if (lineType === 'rental') renderRentalQuote();
        updateAddButton();
    });

    const today = new Date().toISOString().slice(0, 10);
    if (startDate) startDate.min = today;
    if (endDate) endDate.min = today;

    document.getElementById('add-cart-form')?.addEventListener('submit', (event) => {
        const variant = currentVariant();
        const lineType = document.getElementById('cart-line-type')?.value;
        if (cartVariantInput) cartVariantInput.value = variant ? String(variant.id) : '';
        document.getElementById('cart-start').value = startDate?.value || '';
        document.getElementById('cart-end').value = endDate?.value || '';
        if (!variant) {
            event.preventDefault();
            alert('Chọn đủ phân loại sản phẩm.');
            return;
        }
        if (lineType === 'rental' && (!startDate?.value || !endDate?.value)) {
            event.preventDefault();
            alert('Chọn ngày nhận và trả trước khi thuê.');
            return;
        }
        if (lineType === 'rental' && rentalFree <= 0) {
            event.preventDefault();
            alert('Không còn món thuê trống trong khoảng ngày này.');
            return;
        }
        if (lineType === 'rental' && Number(cartQty?.value) > rentalFree) {
            event.preventDefault();
            alert('Chỉ còn ' + rentalFree + ' món trống. Giảm số lượng thuê.');
            return;
        }
        if (lineType === 'sale' && !variant?.sale_price) {
            event.preventDefault();
            alert('Biến thể này không bán.');
            return;
        }
        if (lineType === 'sale' && saleFree <= 0) {
            event.preventDefault();
            alert('Hết hàng bán.');
            return;
        }
        if (lineType === 'sale' && Number(cartQty?.value) > saleFree) {
            event.preventDefault();
            alert('Chỉ còn ' + saleFree + ' sản phẩm. Giảm số lượng mua.');
        }
    });

    pickInitialVariant();
    updateAddButton();
})();
