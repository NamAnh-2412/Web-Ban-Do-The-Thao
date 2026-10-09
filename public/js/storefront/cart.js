(function () {
    function closeModal(modal) {
        if (!modal) {
            return;
        }
        modal.hidden = true;
        document.body.classList.remove('cart-modal-open');
    }

    function openModal(modal) {
        if (!modal) {
            return;
        }
        document.querySelectorAll('[data-cart-modal]:not([hidden])').forEach(function (open) {
            closeModal(open);
        });
        modal.hidden = false;
        document.body.classList.add('cart-modal-open');
        var focusable = modal.querySelector('select, button, a, input');
        if (focusable) {
            focusable.focus();
        }
    }

    document.addEventListener('click', function (event) {
        var openBtn = event.target.closest('[data-cart-detail-open]');
        if (openBtn) {
            var lineId = openBtn.getAttribute('data-line-id');
            openModal(document.getElementById('cart-detail-' + lineId));
            return;
        }

        if (event.target.closest('[data-cart-detail-close]')) {
            closeModal(event.target.closest('[data-cart-modal]'));
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }
        document.querySelectorAll('[data-cart-modal]:not([hidden])').forEach(closeModal);
    });
})();
