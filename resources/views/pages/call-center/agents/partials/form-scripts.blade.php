(function () {
    function slugPart(value) {
        return String(value || '')
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '')
            .slice(0, 24);
    }

    var firstName = document.querySelector('[data-username-source="first"]');
    var lastName = document.querySelector('[data-username-source="last"]');
    var preview = document.querySelector('[data-username-preview]');

    function updateUsernamePreview() {
        if (!preview) {
            return;
        }

        var first = slugPart(firstName ? firstName.value : '');
        var last = slugPart(lastName ? lastName.value : '');

        if (!first && !last) {
            preview.value = '';
            return;
        }

        preview.value = 'cca.' + [first, last].filter(Boolean).join('.');
    }

    if (firstName) {
        firstName.addEventListener('input', updateUsernamePreview);
    }
    if (lastName) {
        lastName.addEventListener('input', updateUsernamePreview);
    }
    updateUsernamePreview();

    document.querySelectorAll('[data-password-toggle]').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            var container = toggle.closest('.password-container');
            var input = container ? container.querySelector('[data-password-input], .password') : null;

            if (!input) {
                return;
            }

            var isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            toggle.classList.toggle('ri-eye-off-line', !isHidden);
            toggle.classList.toggle('ri-eye-line', isHidden);
            toggle.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        });
    });

    document.querySelectorAll('[data-permission-group-toggle]').forEach(function (groupToggle) {
        var group = groupToggle.getAttribute('data-permission-group-toggle');

        function groupBoxes() {
            return document.querySelectorAll('[data-permission-group="' + group + '"]');
        }

        function syncGroupToggle() {
            var boxes = Array.prototype.slice.call(groupBoxes());
            groupToggle.checked = boxes.length > 0 && boxes.every(function (box) {
                return box.checked;
            });
            groupToggle.indeterminate = boxes.some(function (box) {
                return box.checked;
            }) && !groupToggle.checked;
        }

        groupToggle.addEventListener('change', function () {
            groupBoxes().forEach(function (box) {
                box.checked = groupToggle.checked;
            });
            groupToggle.indeterminate = false;
        });

        groupBoxes().forEach(function (box) {
            box.addEventListener('change', syncGroupToggle);
        });

        syncGroupToggle();
    });

    document.querySelectorAll('[data-ui-only-form]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var notice = document.querySelector('[data-ui-preview-notice]');

            if (notice) {
                notice.classList.remove('d-none');
                notice.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
})();
