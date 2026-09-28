(function () {
    const boot = document.getElementById('checkout-ghn-config');
    if (!boot) {
        return;
    }

    const cfg = JSON.parse(boot.textContent);
    const baseGrand = Number(cfg.baseGrand || 0);
    const itemCount = Number(cfg.itemCount || 1);

    const pickup = document.getElementById('ship-pickup');
    const delivery = document.getElementById('ship-delivery');
    const ghnFields = document.getElementById('ghn-fields');
    const codWrap = document.getElementById('pay-cod-wrap');
    const province = document.getElementById('to_province_id');
    const district = document.getElementById('to_district_id');
    const ward = document.getElementById('to_ward_code');
    const shipRow = document.getElementById('ship-fee-row');
    const shipValue = document.getElementById('ship-fee-value');
    const grand = document.getElementById('grand-total');
    const payBank = document.getElementById('pay-bank');

    function money(n) {
        return new Intl.NumberFormat('vi-VN').format(n);
    }

    function setFee(amount) {
        if (!delivery.checked || amount <= 0) {
            shipRow.classList.add('d-none');
            grand.textContent = money(baseGrand);
            return;
        }
        shipRow.classList.remove('d-none');
        shipValue.textContent = money(amount);
        grand.textContent = money(baseGrand + amount);
    }

    function toggleShip() {
        const on = delivery.checked;
        ghnFields.classList.toggle('d-none', !on);
        codWrap.classList.toggle('d-none', !on);
        if (!on && document.getElementById('pay-cod').checked) {
            payBank.checked = true;
        }
        if (!on) setFee(0);
    }

    async function fillSelect(el, url, valueKey, labelKey) {
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        const json = await res.json();
        el.innerHTML = '<option value="">Chọn</option>';
        (json.data || []).forEach((row) => {
            const opt = document.createElement('option');
            opt.value = row[valueKey];
            opt.textContent = row[labelKey];
            el.appendChild(opt);
        });
    }

    async function loadFee() {
        if (!delivery.checked || !district.value || !ward.value) {
            setFee(0);
            return;
        }
        const url = cfg.feeUrl + '?to_district_id=' + encodeURIComponent(district.value)
            + '&to_ward_code=' + encodeURIComponent(ward.value)
            + '&item_count=' + itemCount;
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        const json = await res.json();
        setFee(Number(json.data?.total || 0));
    }

    pickup.addEventListener('change', toggleShip);
    delivery.addEventListener('change', () => {
        toggleShip();
        if (delivery.checked && province.options.length <= 1) {
            fillSelect(province, cfg.provincesUrl, 'ProvinceID', 'ProvinceName');
        }
    });
    province.addEventListener('change', async () => {
        district.innerHTML = '<option value="">Chọn</option>';
        ward.innerHTML = '<option value="">Chọn</option>';
        if (province.value) {
            await fillSelect(district, cfg.districtsUrl + '?province_id=' + province.value, 'DistrictID', 'DistrictName');
        }
        setFee(0);
    });
    district.addEventListener('change', async () => {
        ward.innerHTML = '<option value="">Chọn</option>';
        if (district.value) {
            await fillSelect(ward, cfg.wardsUrl + '?district_id=' + district.value, 'WardCode', 'WardName');
        }
        setFee(0);
    });
    ward.addEventListener('change', loadFee);
    toggleShip();
})();
