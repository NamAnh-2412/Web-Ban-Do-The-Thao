(function () {
    function perView() {
        if (window.matchMedia('(max-width: 767.98px)').matches) {
            return 1;
        }
        if (window.matchMedia('(max-width: 1199.98px)').matches) {
            return 2;
        }
        return 3;
    }

    function initCarousel(root) {
        var track = root.querySelector('[data-sport-carousel-track]');
        var slides = Array.prototype.slice.call(root.querySelectorAll('[data-sport-carousel-slide]'));
        var prev = root.querySelector('[data-sport-carousel-prev]');
        var next = root.querySelector('[data-sport-carousel-next]');
        if (!track || slides.length === 0) {
            return;
        }

        var index = 0;

        function gapPx() {
            var styles = window.getComputedStyle(track);
            return parseFloat(styles.columnGap || styles.gap || '0') || 0;
        }

        function maxIndex() {
            return Math.max(0, slides.length - perView());
        }

        function render() {
            var max = maxIndex();
            if (index > max) {
                index = max;
            }
            if (index < 0) {
                index = 0;
            }

            var slideWidth = slides[0].getBoundingClientRect().width;
            var offset = index * (slideWidth + gapPx());
            track.style.transform = 'translate3d(-' + offset + 'px, 0, 0)';

            slides.forEach(function (slide, i) {
                if (i >= index && i < index + perView()) {
                    slide.setAttribute('aria-current', 'true');
                } else {
                    slide.removeAttribute('aria-current');
                }
            });

            if (prev) {
                prev.disabled = index <= 0;
            }
            if (next) {
                next.disabled = index >= max;
            }
        }

        if (prev) {
            prev.addEventListener('click', function () {
                index -= 1;
                render();
            });
        }
        if (next) {
            next.addEventListener('click', function () {
                index += 1;
                render();
            });
        }

        var resizeTimer = null;
        window.addEventListener('resize', function () {
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(render, 80);
        });

        if (typeof ResizeObserver !== 'undefined') {
            new ResizeObserver(render).observe(root);
        }

        render();
    }

    document.querySelectorAll('[data-sport-carousel]').forEach(initCarousel);
})();
