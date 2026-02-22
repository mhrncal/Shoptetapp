/* ============================================================
   ShopCode — globální JavaScript
   ============================================================ */

$(function () {

    // ---- Auto-dismiss flash zpráv po 5s ------------------
    setTimeout(function () {
        $('.alert.fade.show').each(function () {
            var alert = bootstrap.Alert.getOrCreateInstance(this);
            alert.close();
        });
    }, 5000);

    // ---- Sidebar toggle (mobile) -------------------------
    $('#sidebarToggle').on('click', function () {
        $('#sidebar').toggleClass('open');
    });

    // Klik mimo sidebar na mobilu zavře sidebar
    $(document).on('click', function (e) {
        if ($(window).width() < 768) {
            if (!$(e.target).closest('#sidebar').length && !$(e.target).closest('#sidebarToggle').length) {
                $('#sidebar').removeClass('open');
            }
        }
    });

    // ---- Potvrzovací dialogy (data-confirm) --------------
    $(document).on('submit', 'form[data-confirm]', function (e) {
        var msg = $(this).data('confirm') || 'Opravdu chcete provést tuto akci?';
        if (!confirm(msg)) {
            e.preventDefault();
        }
    });

    $(document).on('click', '[data-confirm]', function (e) {
        if ($(this).is('form')) return;
        var msg = $(this).data('confirm') || 'Opravdu chcete provést tuto akci?';
        if (!confirm(msg)) {
            e.preventDefault();
        }
    });

    // ---- Tooltip inicializace (Bootstrap) ----------------
    $('[data-bs-toggle="tooltip"]').each(function () {
        new bootstrap.Tooltip(this);
    });

    // ---- AJAX helper — přidá CSRF token automaticky -----
    $.ajaxSetup({
        beforeSend: function (xhr) {
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        }
    });

    // ---- Active nav link highlight ----------------------
    // (řeší se PHP v layoutu, toto je záloha)

});

// Globální helper — zobraz toast notifikaci
function showToast(message, type) {
    type = type || 'success';
    var colors = {
        success: 'bg-success',
        error:   'bg-danger',
        warning: 'bg-warning text-dark',
        info:    'bg-info text-dark',
    };
    var $toast = $('<div>')
        .addClass('toast align-items-center text-white border-0 show ' + (colors[type] || 'bg-secondary'))
        .attr('role', 'alert')
        .html('<div class="d-flex"><div class="toast-body">' + message + '</div>' +
              '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>');

    if (!$('#toast-container').length) {
        $('body').append('<div id="toast-container" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:9999"></div>');
    }
    $('#toast-container').append($toast);

    setTimeout(function () {
        $toast.removeClass('show');
        setTimeout(function () { $toast.remove(); }, 300);
    }, 4000);
}
