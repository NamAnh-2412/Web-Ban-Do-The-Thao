(function () {
    function initComposer(root) {
        var typeInput = root.querySelector('[data-attach-type]');
        var idInput = root.querySelector('[data-attach-id]');
        var body = root.querySelector('[data-chat-body]');
        var chip = root.querySelector('[data-attach-chip]');
        var chipLabel = root.querySelector('[data-attach-chip-label]');
        var clearBtn = root.querySelector('[data-attach-clear]');

        function closePickers() {
            document.querySelectorAll('dialog.chat-picker[open]').forEach(function (dialog) {
                dialog.close();
            });
        }

        function showChip() {
            var type = typeInput.value;
            var label = chipLabel.getAttribute('data-current-label') || '';
            if (!type || !idInput.value) {
                chip.classList.add('d-none');
                return;
            }
            chipLabel.textContent = (type === 'order' ? 'Đơn hàng: ' : 'Sản phẩm: ') + label;
            chip.classList.remove('d-none');
        }

        function clearAttachment() {
            typeInput.value = '';
            idInput.value = '';
            chipLabel.setAttribute('data-current-label', '');
            chip.classList.add('d-none');
        }

        root.querySelectorAll('[data-open-picker]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var kind = btn.getAttribute('data-open-picker');
                var dialog = document.querySelector('dialog.chat-picker[data-picker="' + kind + '"]');
                if (dialog) {
                    dialog.showModal();
                }
            });
        });

        document.querySelectorAll('dialog.chat-picker').forEach(function (dialog) {
            var filter = dialog.querySelector('[data-picker-filter]');
            if (filter) {
                filter.addEventListener('input', function () {
                    var q = (filter.value || '').trim().toLowerCase();
                    dialog.querySelectorAll('[data-pick]').forEach(function (item) {
                        var hay = item.getAttribute('data-search') || '';
                        item.classList.toggle('d-none', q !== '' && hay.indexOf(q) === -1);
                    });
                });
            }

            dialog.querySelectorAll('[data-close-picker]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    dialog.close();
                });
            });

            dialog.addEventListener('click', function (event) {
                if (event.target === dialog) {
                    dialog.close();
                }
            });
        });

        document.querySelectorAll('[data-pick]').forEach(function (item) {
            item.addEventListener('click', function () {
                typeInput.value = item.getAttribute('data-type') || '';
                idInput.value = item.getAttribute('data-id') || '';
                chipLabel.setAttribute('data-current-label', item.getAttribute('data-label') || '');
                showChip();
                closePickers();
            });
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', clearAttachment);
        }

        root.addEventListener('submit', function (event) {
            var text = (body.value || '').trim();
            var hasAttach = typeInput.value !== '' && idInput.value !== '';
            if (!text && !hasAttach) {
                event.preventDefault();
                body.focus();
            }
        });

        if (typeInput.value && idInput.value) {
            var oldLabel = typeInput.value === 'order'
                ? ('Đơn #' + idInput.value)
                : ('#' + idInput.value);
            chipLabel.setAttribute('data-current-label', oldLabel);
            showChip();
        }
    }

    document.querySelectorAll('[data-chat-composer]').forEach(initComposer);
})();
