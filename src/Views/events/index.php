<?php $pageTitle = 'Kalendář akcí'; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-calendar-event me-2"></i>Kalendář akcí</h4>
        <?php if ($upcoming > 0): ?>
        <p class="text-muted small mb-0"><strong class="text-success"><?= $upcoming ?></strong> nadcházejících akcí</p>
        <?php endif; ?>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addEventModal">
        <i class="bi bi-plus me-1"></i>Přidat akci
    </button>
</div>

<!-- Taby -->
<ul class="nav nav-tabs border-secondary mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'upcoming' ? 'active' : 'text-muted' ?>"
           href="?tab=upcoming">Nadcházející</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'past' ? 'active' : 'text-muted' ?>"
           href="?tab=past">Proběhlé</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'all' ? 'active' : 'text-muted' ?>"
           href="?tab=all">Vše</a>
    </li>
</ul>

<?php if (empty($events)): ?>
<div class="card border-0">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-calendar-x fs-1 d-block mb-3"></i>
        <p>Žádné akce<?= $tab === 'upcoming' ? ' v budoucnosti' : ($tab === 'past' ? ' v minulosti' : '') ?>.</p>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addEventModal">
            <i class="bi bi-plus me-1"></i>Přidat akci
        </button>
    </div>
</div>
<?php else: ?>
<div class="row g-4">
    <?php foreach ($events as $ev):
        $isPast    = strtotime($ev['end_date']) < time();
        $isToday   = date('Y-m-d', strtotime($ev['start_date'])) === date('Y-m-d');
        $startFmt  = date('d.m.Y H:i', strtotime($ev['start_date']));
        $endFmt    = date('d.m.Y H:i', strtotime($ev['end_date']));
        $sameDay   = date('Y-m-d', strtotime($ev['start_date'])) === date('Y-m-d', strtotime($ev['end_date']));
    ?>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card border-0 h-100 <?= !$ev['is_active'] ? 'opacity-75' : '' ?>">
            <?php if ($ev['image_url']): ?>
            <img src="<?= $e($ev['image_url']) ?>" class="card-img-top"
                 style="height:160px;object-fit:cover;" alt=""
                 onerror="this.style.display='none'">
            <?php endif; ?>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h5 class="fw-bold mb-0 me-2"><?= $e($ev['title']) ?></h5>
                    <div class="d-flex gap-1 flex-shrink-0">
                        <?php if (!$ev['is_active']): ?>
                        <span class="badge bg-secondary">Neaktivní</span>
                        <?php elseif ($isPast): ?>
                        <span class="badge bg-secondary">Proběhlá</span>
                        <?php elseif ($isToday): ?>
                        <span class="badge bg-success">Dnes!</span>
                        <?php else: ?>
                        <span class="badge bg-primary">Aktivní</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Datum -->
                <div class="d-flex align-items-center gap-2 mb-2 text-muted small">
                    <i class="bi bi-calendar3"></i>
                    <span>
                        <?= $startFmt ?>
                        <?php if ($sameDay): ?>
                        – <?= date('H:i', strtotime($ev['end_date'])) ?>
                        <?php else: ?>
                        <br><i class="bi bi-arrow-right ms-4"></i> <?= $endFmt ?>
                        <?php endif; ?>
                    </span>
                </div>

                <?php if ($ev['address']): ?>
                <div class="d-flex align-items-center gap-2 mb-2 text-muted small">
                    <i class="bi bi-geo-alt"></i>
                    <span><?= $e($ev['address']) ?></span>
                </div>
                <?php endif; ?>

                <?php if ($ev['description']): ?>
                <p class="text-muted small mb-3" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                    <?= $e($ev['description']) ?>
                </p>
                <?php endif; ?>

                <div class="d-flex gap-2 mt-auto">
                    <?php if ($ev['event_url']): ?>
                    <a href="<?= $e($ev['event_url']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                    <?php endif; ?>
                    <?php if ($ev['google_maps_url']): ?>
                    <a href="<?= $e($ev['google_maps_url']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-map"></i>
                    </a>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-outline-secondary ms-auto"
                            onclick="editEvent(<?= htmlspecialchars(json_encode($ev), ENT_QUOTES) ?>)">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <form method="POST" action="<?= APP_URL ?>/events/<?= $ev['id'] ?>"
                          onsubmit="return confirm('Smazat tuto akci?')">
                        <input type="hidden" name="_csrf"   value="<?= $e($csrfToken) ?>">
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal: Přidat akci -->
<div class="modal fade" id="addEventModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Přidat akci</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?= APP_URL ?>/events">
                <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                <div class="modal-body">
                    <?= \ShopCode\Core\View::partial('events/_form', ['event' => null]) ?>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zrušit</button>
                    <button type="submit" class="btn btn-primary">Přidat</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Upravit akci -->
<div class="modal fade" id="editEventModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Upravit akci</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editEventForm" action="">
                <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                <div class="modal-body" id="editEventBody">
                    <?= \ShopCode\Core\View::partial('events/_form', ['event' => null]) ?>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zrušit</button>
                    <button type="submit" class="btn btn-primary">Uložit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editEvent(ev) {
    var form = document.getElementById('editEventForm');
    form.action = '<?= APP_URL ?>/events/' + ev.id;
    var body = document.getElementById('editEventBody');

    // Převedeme MySQL datetime na datetime-local formát (YYYY-MM-DDTHH:MM)
    var toLocal = function(dt) { return dt ? dt.substring(0,16).replace(' ','T') : ''; };

    body.querySelector('[name="title"]').value        = ev.title       || '';
    body.querySelector('[name="description"]').value  = ev.description || '';
    body.querySelector('[name="start_date"]').value   = toLocal(ev.start_date);
    body.querySelector('[name="end_date"]').value     = toLocal(ev.end_date);
    body.querySelector('[name="event_url"]').value    = ev.event_url   || '';
    body.querySelector('[name="image_url"]').value    = ev.image_url   || '';
    body.querySelector('[name="address"]').value      = ev.address     || '';
    body.querySelector('[name="google_maps_url"]').value = ev.google_maps_url || '';
    body.querySelector('[name="is_active"]').checked  = ev.is_active == 1;

    new bootstrap.Modal(document.getElementById('editEventModal')).show();
}
</script>
