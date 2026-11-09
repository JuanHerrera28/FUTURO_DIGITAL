/* Futuro Digital · interacciones de interfaz */
(function () {
    'use strict';

    // Mostrar / ocultar contraseña.
    document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.querySelector(button.getAttribute('data-toggle-password'));
            if (!input) return;
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            var icon = button.querySelector('i');
            if (icon) {
                icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
            }
        });
    });

    // Copiar credenciales o textos al portapapeles.
    document.querySelectorAll('[data-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            var text = button.getAttribute('data-copy') || '';
            if (!navigator.clipboard) return;
            navigator.clipboard.writeText(text).then(function () {
                var original = button.innerHTML;
                button.innerHTML = '<i class="bi bi-check2 me-1"></i>Copiado';
                setTimeout(function () { button.innerHTML = original; }, 1800);
            });
        });
    });

    // Confirmación para acciones destructivas o irreversibles.
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });

    // Autoenvío de filtros al cambiar un select.
    document.querySelectorAll('[data-auto-submit]').forEach(function (select) {
        select.addEventListener('change', function () {
            var form = select.closest('form');
            if (form) form.submit();
        });
    });

    // Selección masiva de asistencia.
    document.querySelectorAll('[data-attendance-all]').forEach(function (button) {
        button.addEventListener('click', function () {
            var value = button.getAttribute('data-attendance-all');
            document.querySelectorAll('select[data-attendance]').forEach(function (select) {
                select.value = value;
            });
        });
    });

    // Cálculo visual del total de pesos de evaluación.
    var weightInputs = document.querySelectorAll('[data-weight]');
    if (weightInputs.length) {
        var update = function () {
            var total = 0;
            weightInputs.forEach(function (input) { total += parseFloat(input.value || '0') || 0; });
            document.querySelectorAll('[data-weight-total]').forEach(function (el) {
                el.textContent = total.toFixed(1) + ' %';
                el.classList.toggle('text-danger', Math.abs(total - 100) > 0.01);
                el.classList.toggle('text-success', Math.abs(total - 100) <= 0.01);
            });
        };
        weightInputs.forEach(function (input) { input.addEventListener('input', update); });
        update();
    }

    // Inicializar tooltips de Bootstrap.
    if (window.bootstrap && bootstrap.Tooltip) {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            new bootstrap.Tooltip(el);
        });
    }
})();
