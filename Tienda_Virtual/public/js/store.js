/* Gerson's Store · interacciones de la tienda.
 * JavaScript nativo y progresivo: todo funciona sin JS (formularios y enlaces
 * normales); este archivo solo mejora la experiencia cuando está disponible. */
(function () {
    'use strict';

    var config = window.SF_CONFIG || {};
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    var root = document.documentElement;

    function $(selector, scope) {
        return (scope || document).querySelector(selector);
    }

    function $$(selector, scope) {
        return Array.prototype.slice.call((scope || document).querySelectorAll(selector));
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char];
        });
    }

    var icons = {
        check: '<path d="m5 12 5 5 9-10"/>',
        close: '<path d="M6 6l12 12M18 6 6 18"/>',
        plus: '<path d="M12 5v14M5 12h14"/>',
        minus: '<path d="M5 12h14"/>',
        trash: '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
        bag: '<path d="M6 7h12l-1 13H7L6 7Z"/><path d="M9 7a3 3 0 0 1 6 0"/>'
    };

    function icon(name) {
        return '<svg class="sf-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + (icons[name] || '') + '</svg>';
    }

    /* ---------- Header: estado compacto al desplazarse ---------- */

    function initHeader() {
        var header = $('[data-header]');

        if (!header) {
            return;
        }

        if ('IntersectionObserver' in window) {
            var sentinel = document.createElement('div');
            sentinel.setAttribute('aria-hidden', 'true');
            sentinel.style.cssText = 'position:absolute;top:0;left:0;width:1px;height:24px;pointer-events:none;';
            document.body.prepend(sentinel);

            new IntersectionObserver(function (entries) {
                header.classList.toggle('is-scrolled', !entries[0].isIntersecting);
            }).observe(sentinel);
        }

        var searchToggle = $('[data-search-toggle]');
        var searchInput = $('#sf-search-input');

        if (searchToggle) {
            searchToggle.addEventListener('click', function () {
                var open = !header.classList.contains('is-search-open');
                header.classList.toggle('is-search-open', open);
                searchToggle.setAttribute('aria-expanded', String(open));

                if (open && searchInput) {
                    searchInput.focus();
                }
            });
        }
    }

    /* ---------- Drawers (menú y carrito) ---------- */

    var activeDrawer = null;
    var lastFocus = null;

    function openDrawer(drawer) {
        if (!drawer || activeDrawer === drawer) {
            return;
        }

        if (activeDrawer) {
            closeDrawer(activeDrawer, true);
        }

        lastFocus = document.activeElement;
        activeDrawer = drawer;
        drawer.hidden = false;
        root.classList.add('sf-lock');
        $$('[aria-controls="' + drawer.id + '"]').forEach(function (trigger) {
            trigger.setAttribute('aria-expanded', 'true');
        });

        // Forzar un frame para que la transición de entrada se ejecute.
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                drawer.classList.add('is-open');
            });
        });

        var panel = $('.sf-drawer-panel', drawer);
        if (panel) {
            panel.focus({ preventScroll: true });
        }
    }

    function closeDrawer(drawer, keepLock) {
        drawer = drawer || activeDrawer;

        if (!drawer) {
            return;
        }

        drawer.classList.remove('is-open');
        $$('[aria-controls="' + drawer.id + '"]').forEach(function (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
        });

        var finish = function () {
            drawer.hidden = true;
        };

        if (reduceMotion.matches) {
            finish();
        } else {
            window.setTimeout(finish, 320);
        }

        if (activeDrawer === drawer) {
            activeDrawer = null;
        }

        if (!keepLock) {
            root.classList.remove('sf-lock');

            if (lastFocus && typeof lastFocus.focus === 'function') {
                lastFocus.focus({ preventScroll: true });
            }
        }
    }

    function trapFocus(event) {
        if (!activeDrawer || event.key !== 'Tab') {
            return;
        }

        var focusable = $$('a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])', activeDrawer)
            .filter(function (element) {
                return element.offsetParent !== null;
            });

        if (focusable.length === 0) {
            return;
        }

        var first = focusable[0];
        var last = focusable[focusable.length - 1];

        if (event.shiftKey && (document.activeElement === first || !activeDrawer.contains(document.activeElement))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    function initDrawers() {
        document.addEventListener('click', function (event) {
            var closer = event.target.closest('[data-drawer-close]');

            if (closer && activeDrawer && activeDrawer.contains(closer)) {
                closeDrawer();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && activeDrawer) {
                closeDrawer();
            }

            trapFocus(event);
        });

        var navOpen = $('[data-nav-open]');
        var navDrawer = $('[data-drawer="nav"]');

        if (navOpen && navDrawer) {
            navOpen.addEventListener('click', function () {
                openDrawer(navDrawer);
            });
        }
    }

    /* ---------- Toasts ---------- */

    function toast(options) {
        var region = $('[data-toast-region]');

        if (!region) {
            return;
        }

        var element = document.createElement('div');
        element.className = 'sf-toast' + (options.type === 'error' ? ' sf-toast-error' : '');
        element.setAttribute('role', options.type === 'error' ? 'alert' : 'status');
        element.innerHTML =
            '<span class="sf-toast-icon">' + icon(options.type === 'error' ? 'close' : 'check') + '</span>' +
            '<span class="sf-toast-text"><strong>' + escapeHtml(options.title) + '</strong>' +
            (options.detail ? '<small>' + escapeHtml(options.detail) + '</small>' : '') + '</span>' +
            (options.action ? '<button class="sf-toast-action" type="button">' + escapeHtml(options.action.label) + '</button>' : '');

        if (options.action) {
            element.querySelector('.sf-toast-action').addEventListener('click', function () {
                dismiss();
                options.action.onClick();
            });
        }

        // Máximo dos avisos visibles para no tapar la página.
        while (region.children.length >= 2) {
            region.removeChild(region.firstChild);
        }

        region.appendChild(element);

        var timer = window.setTimeout(dismiss, options.duration || 4200);

        function dismiss() {
            window.clearTimeout(timer);
            element.classList.add('is-leaving');
            window.setTimeout(function () {
                element.remove();
            }, reduceMotion.matches ? 0 : 250);
        }
    }

    /* ---------- Carrito ---------- */

    var cartDrawer = $('[data-drawer="cart"]');
    var onCartPage = !!$('[data-cart-page]') || /\/carrito\.php$/.test(window.location.pathname);

    function cartRequest(method, data) {
        var options = {
            method: method,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'fetch', Accept: 'application/json' }
        };

        if (data) {
            options.body = data;
        }

        return fetch(config.cartApi, options).then(function (response) {
            return response.json().catch(function () {
                throw new Error('Respuesta no válida del servidor.');
            });
        });
    }

    function updateCartIndicators(cart, bump) {
        $$('[data-cart-count]').forEach(function (element) {
            element.textContent = cart.count;
            element.classList.toggle('is-empty', cart.count === 0);

            if (bump && !reduceMotion.matches) {
                element.classList.remove('is-bump');
                void element.offsetWidth;
                element.classList.add('is-bump');
            }
        });

        $$('[data-cart-count-text]').forEach(function (element) {
            element.textContent = cart.count;
        });

        $$('[data-cart-subtotal]').forEach(function (element) {
            element.textContent = cart.subtotal;
        });

        $$('[data-cart-open]').forEach(function (element) {
            element.setAttribute('aria-label', 'Carrito, ' + cart.count + ' producto' + (cart.count === 1 ? '' : 's'));
        });
    }

    function renderCartDrawer(cart) {
        var body = $('[data-cart-body]');
        var foot = $('[data-cart-foot]');

        if (!body || !foot) {
            return;
        }

        if (!cart.items.length) {
            body.innerHTML =
                '<div class="sf-cart-empty"><span class="sf-empty-icon">' + icon('bag') + '</span>' +
                '<strong>Tu carrito está vacío</strong><span>Añade productos para verlos aquí.</span>' +
                '<a class="sf-button sf-button-primary" href="' + escapeHtml(config.baseUrl + '/views/catalogo.php') + '">Explorar catálogo</a></div>';
            foot.hidden = true;
            return;
        }

        body.innerHTML = cart.items.map(function (item) {
            var media = item.image
                ? '<img src="' + escapeHtml(item.image) + '" alt="" loading="lazy" width="72" height="72">'
                : '<span class="sf-card-placeholder">' + escapeHtml(item.initial) + '</span>';

            return '<div class="sf-mini-line" data-mini-line data-product-id="' + item.id + '">' +
                '<a class="sf-mini-media" href="' + escapeHtml(item.url) + '" tabindex="-1" aria-hidden="true">' + media + '</a>' +
                '<div class="sf-mini-info">' +
                    '<div class="sf-mini-top"><a class="sf-mini-name" href="' + escapeHtml(item.url) + '">' + escapeHtml(item.name) + '</a>' +
                    '<button class="sf-icon-button sf-icon-button-danger sf-mini-remove" type="button" data-mini-remove aria-label="Eliminar ' + escapeHtml(item.name) + '">' + icon('trash') + '</button></div>' +
                    '<div class="sf-mini-bottom">' +
                        '<div class="sf-qty sf-qty-sm" role="group" aria-label="Cantidad de ' + escapeHtml(item.name) + '">' +
                            '<button type="button" data-mini-step="-1" aria-label="Restar una unidad"' + (item.quantity <= 1 ? ' disabled' : '') + '>' + icon('minus') + '</button>' +
                            '<input type="number" value="' + item.quantity + '" min="1" max="' + item.max + '" readonly tabindex="-1" aria-label="Cantidad">' +
                            '<button type="button" data-mini-step="1" aria-label="Sumar una unidad"' + (item.quantity >= item.max ? ' disabled' : '') + '>' + icon('plus') + '</button>' +
                        '</div>' +
                        '<strong>' + escapeHtml(item.lineTotal) + '</strong>' +
                    '</div>' +
                '</div>' +
            '</div>';
        }).join('');

        foot.hidden = false;
    }

    function loadCart() {
        return cartRequest('GET').then(function (data) {
            if (data && data.cart) {
                renderCartDrawer(data.cart);
                updateCartIndicators(data.cart, false);
            }

            return data;
        }).catch(function () {
            var body = $('[data-cart-body]');

            if (body) {
                body.innerHTML = '<div class="sf-cart-empty"><strong>No se pudo cargar el carrito.</strong>' +
                    '<a class="sf-button sf-button-primary" href="' + escapeHtml(config.cartUrl) + '">Abrir página del carrito</a></div>';
            }
        });
    }

    function openCart() {
        if (!cartDrawer) {
            window.location.href = config.cartUrl;
            return;
        }

        openDrawer(cartDrawer);
        loadCart();
    }

    function postCart(action, productId, quantity) {
        var data = new FormData();
        data.append('action', action);
        data.append('product_id', productId);

        if (quantity !== undefined) {
            data.append('quantity', quantity);
        }

        return cartRequest('POST', data);
    }

    function setButtonState(button, state) {
        if (!button) {
            return;
        }

        button.classList.remove('is-loading', 'is-done');

        if (!button.dataset.originalHtml) {
            button.dataset.originalHtml = button.innerHTML;
        }

        if (state === 'loading') {
            button.classList.add('is-loading');
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.innerHTML = '<span class="sf-spinner" aria-hidden="true"></span>' +
                (button.querySelector('[data-button-label]') ? '<span>Añadiendo…</span>' : '<span class="sf-visually-hidden">Añadiendo…</span>');
            return;
        }

        button.removeAttribute('aria-busy');

        if (state === 'done') {
            button.classList.add('is-done');
            button.innerHTML = icon('check') + (button.classList.contains('sf-card-add')
                ? '<span class="sf-card-add-label">Listo</span>'
                : '<span>Añadido</span>');
            window.setTimeout(function () {
                setButtonState(button, 'idle');
            }, 1600);
            return;
        }

        button.disabled = false;
        button.innerHTML = button.dataset.originalHtml;
    }

    function initAddToCart() {
        document.addEventListener('submit', function (event) {
            var form = event.target.closest('[data-add-to-cart]');

            if (!form || !config.cartApi || !window.fetch) {
                return;
            }

            event.preventDefault();

            var button = form.querySelector('[type="submit"]');
            var data = new FormData(form);
            var card = form.closest('.sf-card');
            var titleLink = card ? card.querySelector('.sf-card-title a') : null;
            var name = form.getAttribute('data-product-name') || (titleLink ? titleLink.textContent.trim() : 'Producto');

            if (button && button.disabled) {
                return;
            }

            setButtonState(button, 'loading');

            postCart('add', data.get('product_id'), data.get('quantity') || 1).then(function (response) {
                if (response.cart) {
                    updateCartIndicators(response.cart, response.ok);
                    renderCartDrawer(response.cart);
                }

                if (!response.ok) {
                    setButtonState(button, 'idle');
                    toast({ type: 'error', title: response.message || 'No se pudo añadir el producto.' });
                    return;
                }

                setButtonState(button, 'done');
                toast({
                    title: response.message || 'Producto añadido al carrito.',
                    detail: name + (response.cart ? ' · Subtotal ' + response.cart.subtotal : ''),
                    action: { label: 'Ver carrito', onClick: openCart }
                });
            }).catch(function () {
                // Si falla la petición, se usa el envío normal del formulario.
                setButtonState(button, 'idle');
                form.submit();
            });
        });

        $$('[data-cart-open]').forEach(function (trigger) {
            trigger.addEventListener('click', function (event) {
                if (onCartPage || event.metaKey || event.ctrlKey || event.shiftKey) {
                    return;
                }

                event.preventDefault();
                openCart();
            });
        });

        // Acciones dentro del carrito lateral.
        var cartBody = $('[data-cart-body]');

        if (!cartBody) {
            return;
        }

        cartBody.addEventListener('click', function (event) {
            var line = event.target.closest('[data-mini-line]');

            if (!line) {
                return;
            }

            var productId = line.getAttribute('data-product-id');
            var remove = event.target.closest('[data-mini-remove]');
            var step = event.target.closest('[data-mini-step]');
            var request = null;

            if (remove) {
                request = postCart('remove', productId);
            } else if (step) {
                var input = line.querySelector('input');
                var next = parseInt(input.value, 10) + parseInt(step.getAttribute('data-mini-step'), 10);
                request = postCart('update', productId, Math.max(1, next));
            }

            if (!request) {
                return;
            }

            line.classList.add('is-busy');
            request.then(function (response) {
                if (response.cart) {
                    renderCartDrawer(response.cart);
                    updateCartIndicators(response.cart, false);
                }

                if (!response.ok && response.message) {
                    toast({ type: 'error', title: response.message });
                }
            }).catch(function () {
                line.classList.remove('is-busy');
                toast({ type: 'error', title: 'No se pudo actualizar el carrito. Inténtalo de nuevo.' });
            });
        });
    }

    /* ---------- Página del carrito: cantidades sin recargar ---------- */

    function initCartPage() {
        var page = $('[data-cart-page]');

        if (!page || !window.fetch) {
            return;
        }

        var timers = {};

        function syncLine(line, cart) {
            var productId = parseInt(line.getAttribute('data-product-id'), 10);
            var item = cart.items.filter(function (entry) {
                return entry.id === productId;
            })[0];

            if (!item) {
                line.classList.add('is-removing');
                window.setTimeout(function () {
                    line.remove();

                    if (!cart.items.length) {
                        window.location.reload();
                    }
                }, reduceMotion.matches ? 0 : 300);
                return;
            }

            var input = line.querySelector('[data-cart-line-qty]');
            var total = line.querySelector('[data-cart-line-total]');

            if (input) {
                input.value = item.quantity;
            }

            if (total) {
                total.textContent = item.lineTotal;
            }
        }

        function send(line, action, quantity) {
            var productId = line.getAttribute('data-product-id');
            line.classList.add('is-busy');

            postCart(action, productId, quantity).then(function (response) {
                line.classList.remove('is-busy');

                if (response.cart) {
                    updateCartIndicators(response.cart, false);
                    syncLine(line, response.cart);
                }

                if (!response.ok || /ajust|quit/i.test(response.message || '')) {
                    toast({ type: response.ok ? 'success' : 'error', title: response.message });
                }
            }).catch(function () {
                // Respaldo: envío tradicional del formulario de la línea.
                var form = line.querySelector('form');
                var button = form && form.querySelector('[value="' + (action === 'remove' ? 'remove_item' : 'update_quantity') + '"]');

                if (form && button) {
                    form.requestSubmit(button);
                }
            });
        }

        page.addEventListener('click', function (event) {
            var line = event.target.closest('[data-cart-line]');

            if (!line) {
                return;
            }

            if (event.target.closest('[data-cart-line-remove]')) {
                event.preventDefault();
                send(line, 'remove');
                return;
            }

            if (event.target.closest('[data-quantity-decrease], [data-quantity-increase]')) {
                // app.js ajusta el valor; se lee en el siguiente ciclo y se agrupan clics rápidos.
                var id = line.getAttribute('data-product-id');
                window.clearTimeout(timers[id]);
                timers[id] = window.setTimeout(function () {
                    send(line, 'update', line.querySelector('[data-cart-line-qty]').value);
                }, 450);
            }
        });

        page.addEventListener('change', function (event) {
            var input = event.target.closest('[data-cart-line-qty]');

            if (input) {
                send(input.closest('[data-cart-line]'), 'update', input.value);
            }
        });

        page.addEventListener('keydown', function (event) {
            var input = event.target.closest('[data-cart-line-qty]');

            if (input && event.key === 'Enter') {
                event.preventDefault();
                input.blur();
                send(input.closest('[data-cart-line]'), 'update', input.value);
            }
        });
    }

    /* ---------- Checkout: dirección según entrega y envío único ---------- */

    function initCheckout() {
        var form = $('[data-checkout-form]');

        if (!form) {
            return;
        }

        var addressField = $('[data-address-field]', form);
        var addressInput = addressField ? addressField.querySelector('textarea') : null;
        var hint = $('[data-address-hint]', form);

        function syncDelivery() {
            var selected = form.querySelector('[data-delivery-option]:checked');
            var pickup = selected && selected.value === 'retiro_tienda';

            if (addressInput) {
                addressInput.required = !pickup;
            }

            if (hint) {
                hint.textContent = pickup ? '(opcional para retiro en tienda)' : '(obligatoria para delivery o envío)';
            }
        }

        $$('[data-delivery-option]', form).forEach(function (option) {
            option.addEventListener('change', syncDelivery);
        });
        syncDelivery();

        form.addEventListener('submit', function (event) {
            var button = $('[data-checkout-submit]', form);

            // Evita pedidos duplicados por doble clic.
            if (form.dataset.submitting === '1') {
                event.preventDefault();
                return;
            }

            form.dataset.submitting = '1';

            if (button) {
                button.setAttribute('aria-busy', 'true');
                button.classList.add('is-loading');
                button.innerHTML = '<span class="sf-spinner" aria-hidden="true"></span><span>Registrando pedido…</span>';
            }

            // Si el usuario vuelve atrás desde WhatsApp, se reactiva el botón.
            window.addEventListener('pageshow', function () {
                form.dataset.submitting = '';
                if (button && button.dataset.originalHtml) {
                    button.innerHTML = button.dataset.originalHtml;
                }
                if (button) {
                    button.removeAttribute('aria-busy');
                    button.classList.remove('is-loading');
                }
            }, { once: true });
        });

        var submitButton = $('[data-checkout-submit]', form);
        if (submitButton) {
            submitButton.dataset.originalHtml = submitButton.innerHTML;
        }
    }

    /* ---------- Aparición progresiva al hacer scroll ---------- */

    function initReveal() {
        var items = $$('[data-reveal]');

        if (!items.length) {
            return;
        }

        if (!('IntersectionObserver' in window) || reduceMotion.matches) {
            items.forEach(function (item) {
                item.classList.add('is-in', 'is-settled');
            });
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) {
                    return;
                }

                var element = entry.target;
                element.classList.add('is-in');
                observer.unobserve(element);

                // Al terminar, se retira el retraso escalonado para que el hover responda al instante.
                window.setTimeout(function () {
                    element.classList.add('is-settled');
                }, 900);
            });
        }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

        items.forEach(function (item) {
            observer.observe(item);
        });
    }

    /* ---------- Parallax muy sutil en la imagen del hero ---------- */

    function initParallax() {
        var target = $('[data-parallax]');
        var desktop = window.matchMedia('(min-width: 900px) and (hover: hover) and (pointer: fine)');

        if (!target || reduceMotion.matches || !desktop.matches || !('IntersectionObserver' in window)) {
            return;
        }

        var visible = false;
        var ticking = false;
        var host = target.closest('.sf-hero') || target;

        function update() {
            ticking = false;

            if (!visible) {
                return;
            }

            var rect = host.getBoundingClientRect();
            var progress = Math.max(-1, Math.min(1, rect.top / window.innerHeight));
            target.style.transform = 'translate3d(0,' + (progress * -24).toFixed(1) + 'px,0)';
        }

        function onScroll() {
            if (!ticking) {
                ticking = true;
                window.requestAnimationFrame(update);
            }
        }

        new IntersectionObserver(function (entries) {
            visible = entries[0].isIntersecting;

            if (visible) {
                window.addEventListener('scroll', onScroll, { passive: true });
            } else {
                window.removeEventListener('scroll', onScroll);
            }
        }).observe(host);
    }

    /* ---------- Catálogo: filtros plegados en móvil ---------- */

    function initFilters() {
        var panel = $('[data-filters-panel]');

        if (!panel) {
            return;
        }

        var hasActive = !!panel.querySelector('.sf-filters-count');

        if (window.matchMedia('(max-width: 1023px)').matches && !hasActive) {
            panel.open = false;
        }
    }

    /* ---------- Galería: transición suave al cambiar imagen ---------- */

    function initGallery() {
        var main = $('[data-product-main-image]');

        if (!main) {
            return;
        }

        $$('[data-product-thumb]').forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                if (reduceMotion.matches) {
                    return;
                }

                main.classList.add('is-swapping');
                main.addEventListener('load', function () {
                    main.classList.remove('is-swapping');
                }, { once: true });
                window.setTimeout(function () {
                    main.classList.remove('is-swapping');
                }, 400);
            });
        });
    }

    function init() {
        initHeader();
        initDrawers();
        initAddToCart();
        initCartPage();
        initCheckout();
        initReveal();
        initParallax();
        initFilters();
        initGallery();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
