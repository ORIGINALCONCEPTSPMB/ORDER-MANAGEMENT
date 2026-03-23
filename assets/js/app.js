/* ====================================================================
   Order Management System — Main JavaScript (app.js)
   ==================================================================== */

document.addEventListener('DOMContentLoaded', function () {

    // ----------------------------------------------------------------
    // 1. Auto-dismiss alerts after 5 seconds
    // ----------------------------------------------------------------
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity .5s';
            alert.style.opacity = '0';
            setTimeout(function () { alert.remove(); }, 500);
        }, 5000);
    });

    // ----------------------------------------------------------------
    // 2. Password visibility toggle
    // ----------------------------------------------------------------
    document.querySelectorAll('.toggle-password').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = document.querySelector(btn.dataset.target || '#password');
            if (!target) return;
            if (target.type === 'password') {
                target.type = 'text';
                btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
            } else {
                target.type = 'password';
                btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
            }
        });
    });

    // ----------------------------------------------------------------
    // 3. Password strength meter
    // ----------------------------------------------------------------
    var pwField = document.getElementById('password') || document.getElementById('new_password');
    var strengthWrap = document.querySelector('.password-strength-wrap');
    if (pwField && strengthWrap) {
        var bar  = strengthWrap.querySelector('.password-strength-bar');
        var text = strengthWrap.querySelector('.password-strength-text');
        pwField.addEventListener('input', function () {
            var val = pwField.value;
            var score = 0;
            if (val.length >= 8)               score++;
            if (/[A-Z]/.test(val))             score++;
            if (/[a-z]/.test(val))             score++;
            if (/[0-9]/.test(val))             score++;
            if (/[^A-Za-z0-9]/.test(val))      score++;

            strengthWrap.className = 'password-strength-wrap';
            if (val.length === 0) {
                if (text) text.textContent = '';
            } else if (score <= 2) {
                strengthWrap.classList.add('strength-weak');
                if (text) text.textContent = 'Weak';
            } else if (score <= 3) {
                strengthWrap.classList.add('strength-medium');
                if (text) text.textContent = 'Medium';
            } else {
                strengthWrap.classList.add('strength-strong');
                if (text) text.textContent = 'Strong';
            }
        });
    }

    // ----------------------------------------------------------------
    // 4. Form validation
    // ----------------------------------------------------------------
    document.querySelectorAll('form[data-validate]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var valid = true;

            // Clear previous errors
            form.querySelectorAll('.form-error').forEach(function (el) { el.remove(); });
            form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });

            // Required fields
            form.querySelectorAll('[required]').forEach(function (field) {
                if (!field.value.trim()) {
                    showFieldError(field, 'This field is required.');
                    valid = false;
                }
            });

            // Email fields
            form.querySelectorAll('input[type="email"]').forEach(function (field) {
                if (field.value.trim() && !isValidEmail(field.value.trim())) {
                    showFieldError(field, 'Please enter a valid email address.');
                    valid = false;
                }
            });

            // Password match
            var pw1 = form.querySelector('.password-match-1');
            var pw2 = form.querySelector('.password-match-2');
            if (pw1 && pw2 && pw1.value && pw2.value && pw1.value !== pw2.value) {
                showFieldError(pw2, 'Passwords do not match.');
                valid = false;
            }

            if (!valid) {
                e.preventDefault();
                var firstError = form.querySelector('.is-invalid');
                if (firstError) firstError.focus();
            }
        });
    });

    function showFieldError(field, message) {
        field.classList.add('is-invalid');
        var err = document.createElement('div');
        err.className = 'form-error';
        err.textContent = message;
        field.parentNode.insertBefore(err, field.nextSibling);
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    // ----------------------------------------------------------------
    // 5. Confirm delete
    // ----------------------------------------------------------------
    document.querySelectorAll('.confirm-delete').forEach(function (el) {
        el.addEventListener('click', function (e) {
            var msg = el.dataset.confirm || 'Are you sure you want to delete this? This action cannot be undone.';
            if (!confirm(msg)) {
                e.preventDefault();
            }
        });
    });

    // ----------------------------------------------------------------
    // 6. Modals
    // ----------------------------------------------------------------
    document.querySelectorAll('[data-modal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var modalId = btn.dataset.modal;
            var modal   = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('open');
                document.body.style.overflow = 'hidden';
            }
        });
    });

    document.querySelectorAll('.modal-close, .modal-backdrop').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (e.target === el) {
                closeModals();
            }
        });
    });

    document.querySelectorAll('.modal-close-btn').forEach(function (btn) {
        btn.addEventListener('click', closeModals);
    });

    function closeModals() {
        document.querySelectorAll('.modal-backdrop').forEach(function (m) { m.classList.remove('open'); });
        document.body.style.overflow = '';
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModals();
    });

    // ----------------------------------------------------------------
    // 7. Sidebar mobile toggle
    // ----------------------------------------------------------------
    var sidebarToggle  = document.getElementById('sidebar-toggle');
    var sidebar        = document.getElementById('sidebar');
    var sidebarOverlay = document.getElementById('sidebar-overlay');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
            if (sidebarOverlay) sidebarOverlay.classList.toggle('open');
            document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function () {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('open');
            document.body.style.overflow = '';
        });
    }

    // ----------------------------------------------------------------
    // 8. Sortable table columns
    // ----------------------------------------------------------------
    document.querySelectorAll('th.sortable').forEach(function (th) {
        th.addEventListener('click', function () {
            var table  = th.closest('table');
            var index  = Array.from(th.parentNode.children).indexOf(th);
            var asc    = th.dataset.sort !== 'asc';
            th.dataset.sort = asc ? 'asc' : 'desc';

            var rows = Array.from(table.querySelectorAll('tbody tr'));
            rows.sort(function (a, b) {
                var aVal = (a.cells[index] ? a.cells[index].textContent.trim() : '');
                var bVal = (b.cells[index] ? b.cells[index].textContent.trim() : '');
                var n1   = parseFloat(aVal), n2 = parseFloat(bVal);
                if (!isNaN(n1) && !isNaN(n2)) return asc ? n1 - n2 : n2 - n1;
                return asc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
            });

            var tbody = table.querySelector('tbody');
            rows.forEach(function (row) { tbody.appendChild(row); });

            // Update sort indicators
            table.querySelectorAll('th.sortable').forEach(function (el) {
                el.textContent = el.textContent.replace(' ↑', '').replace(' ↓', '');
            });
            th.textContent += asc ? ' ↑' : ' ↓';
        });
    });

    // ----------------------------------------------------------------
    // 9. Character counter for textareas
    // ----------------------------------------------------------------
    document.querySelectorAll('textarea[maxlength]').forEach(function (ta) {
        var counter = ta.parentNode.querySelector('.char-counter');
        if (!counter) {
            counter = document.createElement('div');
            counter.className = 'char-counter';
            ta.parentNode.appendChild(counter);
        }
        function update() {
            counter.textContent = ta.value.length + ' / ' + ta.maxLength;
        }
        ta.addEventListener('input', update);
        update();
    });

    // ----------------------------------------------------------------
    // 10. Table search / filter
    // ----------------------------------------------------------------
    var searchInput  = document.getElementById('table-search');
    var searchTable  = document.getElementById('searchable-table');
    if (searchInput && searchTable) {
        searchInput.addEventListener('input', function () {
            var q    = searchInput.value.toLowerCase();
            var rows = searchTable.querySelectorAll('tbody tr');
            rows.forEach(function (row) {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }

    // ----------------------------------------------------------------
    // 11. Select all checkboxes (bulk actions)
    // ----------------------------------------------------------------
    var selectAll = document.getElementById('select-all');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.row-checkbox').forEach(function (cb) {
                cb.checked = selectAll.checked;
            });
        });
    }

    // ----------------------------------------------------------------
    // 12. Flash message close button (alert-close already inline,
    //     but support programmatically added alerts too)
    // ----------------------------------------------------------------
    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('alert-close')) {
            e.target.closest('.alert').remove();
        }
    });

});
