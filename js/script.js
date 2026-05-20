$(function () {
    const $body = $('body');
    const html = s => $('<div>').text(s || '').html();
    const iso = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    const debounce = (fn, wait = 300) => {
        let t;
        return function () { clearTimeout(t); t = setTimeout(fn, wait); };
    };

    function openModal() { $('#qr-modal').css('display', 'flex'); $body.css('overflow', 'hidden'); }
    function closeModal() { $('#qr-modal').hide(); $body.css('overflow', ''); }

    $('#menu-toggle').on('click', function (e) {
        e.stopPropagation();
        $('#main-nav').toggleClass('open');
        $(this).attr('aria-expanded', $('#main-nav').hasClass('open'));
    });
    $(document).on('click', e => {
        if (!$(e.target).closest('#menu-toggle,#main-nav').length) {
            $('#main-nav').removeClass('open');
            $('#menu-toggle').attr('aria-expanded', 'false');
        }
    });

    $(document).on('click', '[data-confirm]', function (e) {
        if (!confirm($(this).data('confirm'))) e.preventDefault();
    });

    const toggleAssoc = () => $('.association-field').toggle($('#role-select').val() === 'organizer');
    $('#role-select').on('change', toggleAssoc);
    toggleAssoc();

    $('[data-count]').each(function () {
        const $el = $(this), target = parseInt($el.data('count'), 10);
        if (!target) return;
        $({ n: 0 }).animate({ n: target }, {
            duration: 1000,
            step: n => $el.text(Math.ceil(n)),
            complete: () => $el.text(target)
        });
    });

    $('#cookie-ok-btn').on('click', function () {
        document.cookie = 'cookie_ok=1; path=/; max-age=' + (30 * 24 * 3600);
        $('#cookie-banner').fadeOut(150);
    });

    const $filters = $('.filters'), $events = $('#events');
    if ($filters.length && $events.length) {
        const loadEvents = () => {
            const params = new URLSearchParams(new FormData($filters[0]));
            params.set('ajax', '1');
            $events.css('opacity', .5).load('index.php?' + params, function () {
                $events.css('opacity', 1);
                params.delete('ajax');
                history.replaceState(null, '', '?' + params);
            });
        };
        $filters.on('submit', e => { e.preventDefault(); loadEvents(); });
        $filters.on('input change', 'input,select', debounce(loadEvents));
    }

    $('#qr-open-btn').on('click', openModal);
    $('.qr-trigger').on('click', function () {
        const data = $(this).data();
        $('#qr-event-title').text(data.title);
        $('#qr-event-info').text((data.date || '') + ' - ' + (data.place || ''));
        $('#qr-code-display').text(data.code);
        const canvas = $('#qr-code-canvas').empty()[0];
        if (canvas && window.QRCode) new QRCode(canvas, { text: data.code, width: 200, height: 200, correctLevel: QRCode.CorrectLevel.H });
        openModal();
    });
    $('#qr-close,#qr-modal').on('click', function (e) { if (e.target === this) closeModal(); });
    $(document).on('keydown', e => { if (e.key === 'Escape') closeModal(); });

    $('#avatar-input').on('change', function () {
        if (this.files.length) $(this).closest('form').trigger('submit');
    });
    $('#bio-edit-btn').on('click', () => $('#bio-form').show().css('display', 'grid') && $('#bio-display,#bio-edit-btn').hide());
    $('#bio-cancel-btn').on('click', () => $('#bio-form').hide() && $('#bio-display,#bio-edit-btn').show());

    initCalendar();

    function initCalendar() {
        const $grid = $('[data-calendar-grid]');
        if (!$grid.length || !Array.isArray(window.calendarEvents)) return;

        const $title = $('[data-calendar-title]');
        const $list = $('[data-calendar-list]');
        const $dateTitle = $('[data-selected-date]');
        const monthFmt = new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric' });
        const dateFmt = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
        const publicByDate = groupBy(window.calendarEvents, 'date');
        const personal = Array.isArray(window.personalEvents) ? window.personalEvents.slice() : [];

        let selected = window.initialCalendarDate || iso(new Date());
        let start = new Date(selected + 'T12:00:00');
        let current = new Date(start.getFullYear(), start.getMonth(), 1);

        function renderList() {
            const pub = publicByDate[selected] || [];
            const perso = groupBy(personal, 'date')[selected] || [];
            $dateTitle.text(dateFmt.format(new Date(selected + 'T12:00:00')));
            if (!pub.length && !perso.length) return $list.html('<p class="muted">Aucun evenement ce jour-la.</p>');

            $list.html(pub.map(ev => {
                const price = ev.price > 0 ? ev.price.toFixed(2).replace('.', ',') + ' EUR' : 'Gratuit';
                return `<article class="calendar-event"><span>${html(ev.time)} - ${html(ev.category)}</span><strong><a href="event_detail.php?id=${ev.id}">${html(ev.title)}</a></strong><p>${html(ev.place)} - ${price}</p></article>`;
            }).join('') + perso.map(pe =>
                `<article class="calendar-event personal-cal-event"><span class="cal-perso-badge">Perso${pe.time ? ' - ' + html(pe.time) : ''}</span><strong>${html(pe.title)}</strong><button class="cal-perso-delete" data-id="${pe.id}" title="Supprimer">x</button></article>`
            ).join(''));
        }

        function renderCalendar() {
            const y = current.getFullYear(), m = current.getMonth();
            const first = new Date(y, m, 1);
            const blanks = (first.getDay() + 6) % 7;
            const days = new Date(y, m + 1, 0).getDate();
            const persoByDate = groupBy(personal, 'date');
            const cells = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'].map(d => `<div class="calendar-weekday">${d}</div>`);

            for (let i = 0; i < blanks; i++) cells.push('<div class="calendar-cell muted-cell"></div>');
            for (let day = 1; day <= days; day++) {
                const date = `${y}-${String(m + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const pub = publicByDate[date] || [], perso = persoByDate[date] || [];
                const labels = pub.slice(0, 2).map(ev => `<span>${html(ev.title)}</span>`).join('')
                    + (perso[0] ? `<em class="cal-perso-chip">${html(perso[0].title)}</em>` : '')
                    + (pub.length + perso.length > 3 ? `<em>+${pub.length + perso.length - 3}</em>` : '');
                cells.push(`<button class="calendar-cell ${date === selected ? 'selected' : ''}" type="button" data-date="${date}"><strong>${day}</strong>${labels}</button>`);
            }
            $title.text(monthFmt.format(current));
            $grid.html(cells.join(''));
            renderList();
        }

        $grid.on('click', '[data-date]', function () {
            selected = $(this).data('date');
            $('#personal-event-form').data('date', selected);
            renderCalendar();
        });
        $('[data-calendar-prev]').on('click', () => { current = new Date(current.getFullYear(), current.getMonth() - 1, 1); renderCalendar(); });
        $('[data-calendar-next]').on('click', () => { current = new Date(current.getFullYear(), current.getMonth() + 1, 1); renderCalendar(); });

        $('#personal-event-form').data('date', selected).on('submit', function (e) {
            e.preventDefault();
            const $form = $(this), title = $('#personal-title').val().trim();
            if (!title) return;
            $.post('personal_event.php', { action: 'create', title, date: $form.data('date') || selected, time: $('#personal-time').val() || '' }, data => {
                if (data.id) {
                    personal.push(data);
                    selected = data.date;
                    $form[0].reset();
                    renderCalendar();
                }
            }, 'json');
        });

        $list.on('click', '.cal-perso-delete', function () {
            const id = parseInt($(this).data('id'), 10);
            if (!confirm('Supprimer cet evenement personnel ?')) return;
            $.post('personal_event.php', { action: 'delete', id }, data => {
                if (data.success) {
                    const index = personal.findIndex(p => parseInt(p.id, 10) === id);
                    if (index >= 0) personal.splice(index, 1);
                    renderCalendar();
                }
            }, 'json');
        });

        renderCalendar();
    }

    function groupBy(list, key) {
        return list.reduce((acc, item) => {
            (acc[item[key]] ||= []).push(item);
            return acc;
        }, {});
    }
});
