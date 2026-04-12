(function () {
  'use strict';

  // ── State ──────────────────────────────────────────────────────────────────
  var state = {
    config:       null,   // loaded from Apps Script
    selectedType: null,   // appointment type object
    selectedDate: '',     // 'YYYY-MM-DD'
    selectedTime: '',     // 'HH:MM'
    selectedLabel: '',    // '10:00 AM'
    picker:       null,   // Flatpickr instance
  };

  // ── Init ───────────────────────────────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', function () {
    if (!APPS_SCRIPT_URL) {
      show('setup-banner');
      hide('loading-screen');
      showError('Apps Script URL is not set in <code>assets/js/config.js</code>.');
      return;
    }
    loadConfig();
  });

  function loadConfig() {
    fetch(APPS_SCRIPT_URL + '?action=config')
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.error) throw new Error(data.error);
        state.config = data;
        document.getElementById('nav-title').textContent = (data.ownerName || 'Booking App');
        hide('loading-screen');
        renderTypeCards();
        goTo('type');
      })
      .catch(function (err) {
        hide('loading-screen');
        showError(err.message);
      });
  }

  // ── Step 1: Appointment type cards ────────────────────────────────────────
  function renderTypeCards() {
    var container = document.getElementById('type-cards');
    container.innerHTML = '';
    state.config.appointmentTypes.forEach(function (type) {
      var col = document.createElement('div');
      col.className = 'col-sm-6';
      col.innerHTML =
        '<div class="card h-100 type-card shadow-sm" data-id="' + esc(type.id) + '">' +
          '<div class="card-header text-white fw-semibold" style="background:' + esc(type.color) + '">' +
            '<i class="bi bi-calendar-event me-2"></i>' + esc(type.name) +
          '</div>' +
          '<div class="card-body d-flex flex-column">' +
            (type.description ? '<p class="card-text text-muted small mb-3">' + esc(type.description) + '</p>' : '') +
            '<div class="mt-auto d-flex justify-content-between align-items-center">' +
              '<span class="text-muted small"><i class="bi bi-clock me-1"></i>' + type.duration + ' min</span>' +
              '<button class="btn btn-sm btn-primary">Select</button>' +
            '</div>' +
          '</div>' +
        '</div>';
      col.querySelector('.type-card').addEventListener('click', function () {
        selectType(type);
      });
      container.appendChild(col);
    });
  }

  function selectType(type) {
    state.selectedType = type;
    state.selectedDate = '';
    state.selectedTime = '';
    state.selectedLabel = '';

    // Update type pill
    var pill = document.getElementById('type-pill-label');
    pill.textContent = type.name + ' · ' + type.duration + ' min';
    pill.style.background = type.color;

    // Init/reinit date picker with correct disabled days
    initDatePicker();
    clearSlots();
    goTo('datetime');
  }

  // ── Step 2: Date picker & slots ───────────────────────────────────────────
  function initDatePicker() {
    var wh = state.config.workingHours;

    // Build list of disabled days-of-week
    var disabledDays = [];
    for (var d = 0; d <= 6; d++) {
      if (!wh[d]) disabledDays.push(d);
    }

    if (state.picker) {
      state.picker.destroy();
      state.picker = null;
    }

    state.picker = flatpickr('#datepicker', {
      minDate: 'today',
      maxDate: new Date(Date.now() + 90 * 24 * 60 * 60 * 1000),
      disable: [function (date) { return disabledDays.indexOf(date.getDay()) > -1; }],
      disableMobile: false,
      onChange: function (dates, dateStr) {
        state.selectedDate  = dateStr;
        state.selectedTime  = '';
        state.selectedLabel = '';
        clearSlots();
        loadSlots(dateStr);
      },
    });
  }

  function loadSlots(date) {
    show('slots-spinner');
    hide('slots-empty');
    hide('slots-label');
    document.getElementById('slots-grid').innerHTML = '';

    fetch(APPS_SCRIPT_URL + '?action=slots&typeId=' + encodeURIComponent(state.selectedType.id) + '&date=' + encodeURIComponent(date))
      .then(function (r) { return r.json(); })
      .then(function (data) {
        hide('slots-spinner');
        if (data.error) { show('slots-empty'); return; }
        var slots = data;
        if (!Array.isArray(slots) || slots.length === 0) { show('slots-empty'); return; }
        renderSlots(slots);
      })
      .catch(function () {
        hide('slots-spinner');
        show('slots-empty');
      });
  }

  function renderSlots(slots) {
    show('slots-label');
    var grid = document.getElementById('slots-grid');
    grid.innerHTML = '';
    slots.forEach(function (slot) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-outline-primary slot-btn';
      btn.textContent = slot.label;
      btn.dataset.time = slot.time;
      btn.dataset.label = slot.label;
      btn.addEventListener('click', function () { selectSlot(btn, slot); });
      grid.appendChild(btn);
    });
  }

  function selectSlot(btn, slot) {
    document.querySelectorAll('.slot-btn').forEach(function (b) {
      b.classList.remove('selected');
    });
    btn.classList.add('selected');
    state.selectedTime  = slot.time;
    state.selectedLabel = slot.label;

    // Update summary bar in step 3
    document.getElementById('summary-type').textContent = state.selectedType.name;
    document.getElementById('summary-datetime').textContent = formatDate(state.selectedDate) + ' at ' + slot.label;

    // Move to details after short delay so user sees the selection
    setTimeout(function () { goTo('details'); }, 180);
  }

  function clearSlots() {
    hide('slots-label');
    hide('slots-empty');
    hide('slots-spinner');
    document.getElementById('slots-grid').innerHTML = '';
  }

  // ── Step 3: Client details form ───────────────────────────────────────────
  document.getElementById('details-form').addEventListener('submit', function (e) {
    e.preventDefault();
    submitBooking();
  });

  function submitBooking() {
    var form    = document.getElementById('details-form');
    var nameEl  = document.getElementById('f-name');
    var emailEl = document.getElementById('f-email');
    var errDiv  = document.getElementById('details-error');
    var btn     = document.getElementById('submit-btn');

    hide('details-error');

    // Basic validation
    var errors = [];
    if (!nameEl.value.trim())  errors.push('Client name is required.');
    if (!emailEl.value.trim() || !emailEl.value.includes('@')) errors.push('A valid email is required.');
    if (!state.selectedType || !state.selectedDate || !state.selectedTime) errors.push('Please select a date and time.');

    if (errors.length) {
      errDiv.innerHTML = errors.join('<br>');
      show('details-error');
      return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Confirming…';

    var formData = new FormData();
    formData.append('typeId',        state.selectedType.id);
    formData.append('date',          state.selectedDate);
    formData.append('time',          state.selectedTime);
    formData.append('clientName',    document.getElementById('f-name').value.trim());
    formData.append('clientEmail',   document.getElementById('f-email').value.trim());
    formData.append('clientPhone',   document.getElementById('f-phone').value.trim());
    formData.append('clientAddress', document.getElementById('f-address').value.trim());
    formData.append('notes',         document.getElementById('f-notes').value.trim());

    fetch(APPS_SCRIPT_URL, { method: 'POST', body: formData })
      .then(function (r) { return r.json(); })
      .then(function (result) {
        if (result.success) {
          showSuccess(formData);
        } else {
          errDiv.textContent = result.error || 'Something went wrong. Please try again.';
          show('details-error');
        }
      })
      .catch(function () {
        errDiv.textContent = 'Network error. Please check your connection and try again.';
        show('details-error');
      })
      .finally(function () {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Confirm Booking';
      });
  }

  // ── Step 4: Success ───────────────────────────────────────────────────────
  function showSuccess(formData) {
    document.getElementById('s-type').textContent     = state.selectedType.name;
    document.getElementById('s-date').textContent     = formatDate(state.selectedDate);
    document.getElementById('s-time').textContent     = state.selectedLabel;
    document.getElementById('s-duration').textContent = state.selectedType.duration + ' minutes';
    document.getElementById('s-client').textContent   = formData.get('clientName');
    document.getElementById('s-email').textContent    = formData.get('clientEmail');

    var addr = formData.get('clientAddress');
    if (addr) {
      document.getElementById('s-addr').textContent = addr;
      document.getElementById('s-addr').classList.remove('d-none');
      document.getElementById('s-addr-label').classList.remove('d-none');
    }

    goTo('success');
    document.getElementById('details-form').reset();
  }

  // ── Navigation ────────────────────────────────────────────────────────────
  var STEPS = ['type', 'datetime', 'details', 'success'];
  var STEP_LABELS = { type: 'Step 1 of 3', datetime: 'Step 2 of 3', details: 'Step 3 of 3', success: 'Done' };

  function goTo(step) {
    STEPS.forEach(function (s) { hide('step-' + s); });
    show('step-' + step);
    document.getElementById('nav-step').textContent = STEP_LABELS[step] || '';
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function reset() {
    state.selectedType  = null;
    state.selectedDate  = '';
    state.selectedTime  = '';
    state.selectedLabel = '';
    if (state.picker) { state.picker.clear(); }
    clearSlots();
    document.getElementById('details-form').reset();
    hide('details-error');
    // Reset address row
    document.getElementById('s-addr').classList.add('d-none');
    document.getElementById('s-addr-label').classList.add('d-none');
    goTo('type');
  }

  // ── Utilities ─────────────────────────────────────────────────────────────
  function show(id) { var el = document.getElementById(id); if (el) el.classList.remove('d-none'); }
  function hide(id) { var el = document.getElementById(id); if (el) el.classList.add('d-none'); }

  function showError(msg) {
    hide('loading-screen');
    document.getElementById('error-message').innerHTML = ' ' + msg;
    show('error-screen');
  }

  function esc(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function formatDate(dateStr) {
    if (!dateStr) return '';
    var parts = dateStr.split('-');
    var d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
    return d.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
  }

  // ── Public API (called from HTML) ─────────────────────────────────────────
  window.App = { goTo: goTo, reset: reset };

})();
