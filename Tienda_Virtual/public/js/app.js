document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-year]').forEach(function (element) {
        element.textContent = new Date().getFullYear();
    });

    document.querySelectorAll('[data-product-main-image]').forEach(function (mainImage) {
        var thumbs = Array.from(document.querySelectorAll('[data-product-thumb]'));

        if (thumbs.length === 0) {
            return;
        }

        thumbs.forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                var nextSrc = thumb.getAttribute('data-gallery-src') || '';
                var nextAlt = thumb.getAttribute('data-gallery-alt') || mainImage.getAttribute('alt') || '';

                if (nextSrc !== '') {
                    mainImage.setAttribute('src', nextSrc);
                }

                mainImage.setAttribute('alt', nextAlt);

                thumbs.forEach(function (item) {
                    item.classList.remove('is-active');
                    item.setAttribute('aria-pressed', 'false');
                });

                thumb.classList.add('is-active');
                thumb.setAttribute('aria-pressed', 'true');
            });
        });
    });

    document.querySelectorAll('[data-quantity-selector]').forEach(function (selector) {
        var input = selector.querySelector('[data-quantity-input]');
        var decreaseButton = selector.querySelector('[data-quantity-decrease]');
        var increaseButton = selector.querySelector('[data-quantity-increase]');

        if (!input || !decreaseButton || !increaseButton) {
            return;
        }

        function clampValue(nextValue) {
            var min = parseInt(input.getAttribute('min') || '1', 10);
            var max = parseInt(input.getAttribute('max') || String(Number.MAX_SAFE_INTEGER), 10);
            var normalizedValue = Number.isNaN(nextValue) ? min : nextValue;

            if (normalizedValue < min) {
                normalizedValue = min;
            }

            if (normalizedValue > max) {
                normalizedValue = max;
            }

            input.value = String(normalizedValue);
        }

        decreaseButton.addEventListener('click', function () {
            clampValue(parseInt(input.value || '1', 10) - 1);
        });

        increaseButton.addEventListener('click', function () {
            clampValue(parseInt(input.value || '1', 10) + 1);
        });

        input.addEventListener('input', function () {
            clampValue(parseInt(input.value || '1', 10));
        });
    });

    document.querySelectorAll('[data-select-all]').forEach(function (toggle) {
        var groupName = toggle.getAttribute('data-select-all') || '';

        if (groupName === '') {
            return;
        }

        function groupItems() {
            return Array.from(document.querySelectorAll('[data-select-item="' + groupName + '"]'));
        }

        function syncToggleState() {
            var items = groupItems();
            var checkedItems = items.filter(function (item) {
                return item.checked;
            });
            var countTarget = document.querySelector('[data-selection-count="' + groupName + '"]');
            var actionButtons = Array.from(document.querySelectorAll('[data-selection-action="' + groupName + '"]'));

            if (items.length === 0) {
                toggle.checked = false;
                toggle.indeterminate = false;

                if (countTarget) {
                    countTarget.textContent = '0 pedidos seleccionados';
                }

                actionButtons.forEach(function (button) {
                    button.disabled = true;
                });
                return;
            }

            toggle.checked = checkedItems.length === items.length;
            toggle.indeterminate = checkedItems.length > 0 && checkedItems.length < items.length;

            if (countTarget) {
                countTarget.textContent = checkedItems.length + ' pedido(s) seleccionados';
            }

            actionButtons.forEach(function (button) {
                button.disabled = checkedItems.length === 0;
            });
        }

        toggle.addEventListener('change', function () {
            var nextChecked = toggle.checked;

            groupItems().forEach(function (item) {
                item.checked = nextChecked;
            });

            syncToggleState();
        });

        groupItems().forEach(function (item) {
            item.addEventListener('change', syncToggleState);
        });

        syncToggleState();
    });

    document.querySelectorAll('[data-admin-search-form]').forEach(function (form) {
        var searchInput = form.querySelector('[data-admin-search-input]');
        var submitTimer = null;
        var lastSubmittedValue = searchInput ? searchInput.value : '';

        if (!searchInput) {
            return;
        }

        function submitSearch() {
            var currentValue = searchInput.value;

            if (currentValue === lastSubmittedValue) {
                return;
            }

            lastSubmittedValue = currentValue;
            form.requestSubmit();
        }

        searchInput.addEventListener('input', function () {
            window.clearTimeout(submitTimer);
            submitTimer = window.setTimeout(submitSearch, 350);
        });

        searchInput.addEventListener('search', function () {
            window.clearTimeout(submitTimer);
            submitSearch();
        });

        searchInput.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter') {
                return;
            }

            event.preventDefault();
            window.clearTimeout(submitTimer);
            submitSearch();
        });
    });

    document.querySelectorAll('[data-catalog-form]').forEach(function (form) {
        var autoSubmitFields = Array.from(form.querySelectorAll('[data-catalog-auto-submit]'));

        autoSubmitFields.forEach(function (field) {
            field.addEventListener('change', function () {
                form.requestSubmit();
            });
        });
    });

    document.querySelectorAll('[data-catalog-sidebar-form]').forEach(function (form) {
        var checkboxes = Array.from(form.querySelectorAll('[data-catalog-sidebar-checkbox]'));

        checkboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                form.requestSubmit();
            });
        });
    });

    document.querySelectorAll('[data-catalog-jump-form]').forEach(function (form) {
        var input = form.querySelector('input[name="pagina"]');
        var max = input ? parseInt(input.getAttribute('max') || '1', 10) : 1;

        form.addEventListener('submit', function (event) {
            if (!input) {
                return;
            }

            var value = parseInt(input.value, 10);

            if (Number.isNaN(value) || value < 1) {
                event.preventDefault();
                input.value = '1';
                form.requestSubmit();
                return;
            }

            if (value > max) {
                event.preventDefault();
                input.value = String(max);
                form.requestSubmit();
            }
        });
    });

    document.querySelectorAll('[data-catalog-grid]').forEach(function (grid) {
        var cards = Array.from(grid.querySelectorAll('[data-reveal-card]'));

        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) {
                        return;
                    }

                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                });
            }, {
                rootMargin: '0px 0px -10% 0px',
                threshold: 0.12,
            });

            cards.forEach(function (card, index) {
                card.style.transitionDelay = Math.min(index, 8) * 40 + 'ms';
                observer.observe(card);
            });

            return;
        }

        cards.forEach(function (card) {
            card.classList.add('is-visible');
        });
    });

    document.querySelectorAll('[data-admin-preview-toggle]').forEach(function (button) {
        var panelId = button.getAttribute('aria-controls');
        var panel = panelId ? document.getElementById(panelId) : null;
        var frame = panel ? panel.querySelector('[data-admin-preview-frame]') : null;

        if (!panel || !frame) {
            return;
        }

        button.addEventListener('click', function () {
            var isHidden = panel.hasAttribute('hidden');

            if (isHidden) {
                panel.removeAttribute('hidden');
                button.setAttribute('aria-expanded', 'true');
                button.textContent = 'Ocultar vista rapida';

                if (!frame.getAttribute('src')) {
                    frame.setAttribute('src', frame.getAttribute('data-preview-src') || '');
                }

                panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                return;
            }

            panel.setAttribute('hidden', 'hidden');
            button.setAttribute('aria-expanded', 'false');
            button.textContent = 'Vista rapida';
        });
    });

    document.querySelectorAll('[data-admin-gallery]').forEach(function (gallery) {
        var sortForm = gallery.parentElement.querySelector('[data-admin-sort-form]');
        var orderInput = sortForm ? sortForm.querySelector('[data-admin-image-order]') : null;
        var galleryItems = Array.from(gallery.querySelectorAll('[data-gallery-item]'));
        var draggedItem = null;

        if (!sortForm || !orderInput || galleryItems.length < 2) {
            return;
        }

        function clearDragState() {
            gallery.classList.remove('is-sorting');
            gallery.querySelectorAll('[data-gallery-item]').forEach(function (item) {
                item.classList.remove('is-dragging');
                item.classList.remove('is-drop-target');
            });
            draggedItem = null;
        }

        function submitCurrentOrder() {
            var orderedIds = Array.from(gallery.querySelectorAll('[data-gallery-item]')).map(function (item) {
                return item.getAttribute('data-image-id') || '';
            }).filter(Boolean);

            if (orderedIds.length < 2) {
                clearDragState();
                return;
            }

            orderInput.value = orderedIds.join(',');
            sortForm.submit();
        }

        galleryItems.forEach(function (item) {
            item.addEventListener('dragstart', function (event) {
                draggedItem = item;
                gallery.classList.add('is-sorting');
                item.classList.add('is-dragging');

                if (event.dataTransfer) {
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', item.getAttribute('data-image-id') || '');
                }
            });

            item.addEventListener('dragover', function (event) {
                if (!draggedItem || draggedItem === item) {
                    return;
                }

                event.preventDefault();
                item.classList.add('is-drop-target');

                if (event.dataTransfer) {
                    event.dataTransfer.dropEffect = 'move';
                }
            });

            item.addEventListener('dragleave', function () {
                item.classList.remove('is-drop-target');
            });

            item.addEventListener('drop', function (event) {
                var items;
                var draggedIndex;
                var targetIndex;

                if (!draggedItem || draggedItem === item) {
                    return;
                }

                event.preventDefault();
                items = Array.from(gallery.querySelectorAll('[data-gallery-item]'));
                draggedIndex = items.indexOf(draggedItem);
                targetIndex = items.indexOf(item);

                if (draggedIndex < targetIndex) {
                    gallery.insertBefore(draggedItem, item.nextSibling);
                } else {
                    gallery.insertBefore(draggedItem, item);
                }

                submitCurrentOrder();
            });

            item.addEventListener('dragend', clearDragState);
        });
    });
});
