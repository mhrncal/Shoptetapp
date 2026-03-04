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

    // ---- Sidebar (mobile) --------------------------------
    function openSidebar() {
        $('#sidebar').addClass('open');
        $('#sidebar-overlay').addClass('active');
        $('body').css('overflow', 'hidden');
    }

    function closeSidebar() {
        $('#sidebar').removeClass('open');
        $('#sidebar-overlay').removeClass('active');
        $('body').css('overflow', '');
    }

    $('#sidebarToggle, #mobileMenuToggle').on('click', function (e) {
        e.preventDefault();
        if ($('#sidebar').hasClass('open')) { closeSidebar(); } else { openSidebar(); }
    });

    $('#sidebar-overlay').on('click', function () { closeSidebar(); });

    // Swipe doleva v sidebar = zavřít
    var touchStartX = 0;
    var sidebarEl = document.getElementById('sidebar');
    if (sidebarEl) {
        sidebarEl.addEventListener('touchstart', function (e) {
            touchStartX = e.touches[0].clientX;
        }, { passive: true });
        sidebarEl.addEventListener('touchend', function (e) {
            if (e.changedTouches[0].clientX - touchStartX < -60) closeSidebar();
        }, { passive: true });
    }

    // Swipe z levého okraje = otevřít sidebar
    var edgeX = 0;
    document.addEventListener('touchstart', function (e) { edgeX = e.touches[0].clientX; }, { passive: true });
    document.addEventListener('touchend', function (e) {
        if (edgeX < 20 && e.changedTouches[0].clientX - edgeX > 60 && $(window).width() < 768) openSidebar();
    }, { passive: true });

    // ---- Potvrzovací dialogy (data-confirm) --------------
    $(document).on('submit', 'form[data-confirm]', function (e) {
        var msg = $(this).data('confirm') || 'Opravdu chcete provést tuto akci?';
        if (!confirm(msg)) e.preventDefault();
    });

    $(document).on('click', '[data-confirm]', function (e) {
        if ($(this).is('form')) return;
        var msg = $(this).data('confirm') || 'Opravdu chcete provést tuto akci?';
        if (!confirm(msg)) e.preventDefault();
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

});

// Globální helper — zobraz toast notifikaci
function showToast(message, type) {
    type = type || 'success';
    var colors = { success: 'bg-success', error: 'bg-danger', warning: 'bg-warning text-dark', info: 'bg-info text-dark' };
    var $toast = $('<div>')
        .addClass('toast align-items-center text-white border-0 show ' + (colors[type] || 'bg-secondary'))
        .attr('role', 'alert')
        .html('<div class="d-flex"><div class="toast-body">' + message + '</div>' +
              '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>');
    if (!$('#toast-container').length) {
        $('body').append('<div id="toast-container" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:9999"></div>');
    }
    $('#toast-container').append($toast);
    setTimeout(function () { $toast.removeClass('show'); setTimeout(function () { $toast.remove(); }, 300); }, 4000);
}
