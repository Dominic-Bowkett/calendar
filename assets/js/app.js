(function () {
  'use strict';

  // ── State ──────────────────────────────────────────────────────────────────
  var state = {
    config:        null,  // loaded from Apps Script
    selectedType:  null,  // appointment type object
    selectedDate:  '',    // 'YYYY-MM-DD'
    selectedTime:  '',    // 'HH:MM'
    selectedLabel: '',    // '10:00 AM'
    picker:        null,  // Flatpickr instance
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

    // VAT hint on price input
    document.getElementById('f-price').addEventListener('input', function () {
      var val = parseFloat(this.value);
      updateVatHint(isNaN(val) ? 0 : val);
    });

    // Booking form submit
    document.getElementById('details-form').addEventListener('submit', function (e) {
      e.preventDefault();
      submitBooking();
    });
  });

  // ── Config ────────────────────────────────────────────────────────────────
  function loadConfig() {
    fetch(APPS_SCRIPT_URL + '?action=config')
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.error) throw new Error(data.error);
        state.config = data;
        var heading = document.getElementById('owner-heading');
        if (data.ownerName) heading.textContent = data.ownerName + ' — Book an Appointment';
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
      var priceHtml = type.defaultPrice > 0
        ? '<span class="fw-semibold">£' + type.defaultPrice.toFixed(2) + '</span>'
        : '<span class="badge bg-secondary fw-normal">Free</span>';
      col.innerHTML =
        '<div class="card h-100 type-card shadow-sm border-0">' +
          '<div class="card-header border-0 text-white fw-semibold" style="background:' + esc(type.color) + '">' +
            esc(type.name) +
          '</div>' +
          '<div class="card-body d-flex flex-column">' +
            (type.description ? '<p class="card-text text-muted small mb-3">' + esc(type.description) + '</p>' : '') +
            '<div class="mt-auto d-flex justify-content-between align-items-center">' +
              '<span class="text-muted small"><i class="bi bi-clock me-1"></i>' + esc(String(type.duration)) + ' min</span>' +
              priceHtml +
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
    state.selectedDate  = '';
    state.selectedTime  = '';
    state.selectedLabel = '';

    // Update pill + duration label
    var pill = document.getElementById('type-pill');
    pill.textContent = type.name;
    pill.style.background = type.color;
    document.getElementById('type-duration-label').textContent = type.duration + ' min';

    initDatePicker();
    resetSlots();
    goTo('datetime');
  }

  // ── Step 2: Inline calendar & slots ──────────────────────────────────────
  function initDatePicker() {
    var wh = state.config.workingHours;
    var disabledDays = [];
    for (var d = 0; d <= 6; d++) {
      if (!wh[d]) disabledDays.push(d);
    }

    if (state.picker) {
      state.picker.destroy();
      state.picker = null;
    }

    state.picker = flatpickr('#datepicker', {
      inline: true,
      minDate: 'today',
      maxDate: new Date(Date.now() + 90 * 24 * 60 * 60 * 1000),
      disable: [function (date) { return disabledDays.indexOf(date.getDay()) > -1; }],
      disableMobile: false,
      onChange: function (dates, dateStr) {
        state.selectedDate  = dateStr;
        state.selectedTime  = '';
        state.selectedLabel = '';
        onDateSelected(dateStr);
      },
    });
  }

  function onDateSelected(dateStr) {
    hide('slots-placeholder');
    show('slots-content');
    document.getElementById('slots-date-heading').textContent = formatDate(dateStr);
    show('slots-spinner');
    hide('slots-empty');
    document.getElementById('slots-list').innerHTML = '';
    loadSlots(dateStr);
  }

  function loadSlots(date) {
    fetch(
      APPS_SCRIPT_URL +
      '?action=slots&typeId=' + encodeURIComponent(state.selectedType.id) +
      '&date=' + encodeURIComponent(date)
    )
      .then(function (r) { return r.json(); })
      .then(function (data) {
        hide('slots-spinner');
        if (data.error || !Array.isArray(data) || data.length === 0) {
          show('slots-empty');
          return;
        }
        renderSlots(data);
      })
      .catch(function () {
        hide('slots-spinner');
        show('slots-empty');
      });
  }

  function renderSlots(slots) {
    var list = document.getElementById('slots-list');
    list.innerHTML = '';
    slots.forEach(function (slot) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-outline-primary slot-btn w-100 mb-2';
      btn.textContent = slot.label;
      btn.dataset.time  = slot.time;
      btn.dataset.label = slot.label;
      btn.addEventListener('click', function () { selectSlot(btn, slot); });
      list.appendChild(btn);
    });
  }

  function selectSlot(btn, slot) {
    document.querySelectorAll('.slot-btn').forEach(function (b) {
      b.classList.remove('selected');
    });
    btn.classList.add('selected');
    state.selectedTime  = slot.time;
    state.selectedLabel = slot.label;

    // Populate details step header
    document.getElementById('det-type-name').textContent = state.selectedType.name;
    document.getElementById('det-datetime').textContent  =
      formatDate(state.selectedDate) + ' at ' + slot.label;

    // Pre-fill price from type default
    var priceEl = document.getElementById('f-price');
    if (state.selectedType.defaultPrice > 0) {
      priceEl.value = state.selectedType.defaultPrice.toFixed(2);
      updateVatHint(state.selectedType.defaultPrice);
    } else {
      priceEl.value = '';
      document.getElementById('price-vat-hint').textContent = '';
    }

    setTimeout(function () { goTo('details'); }, 180);
  }

  function resetSlots() {
    show('slots-placeholder');
    hide('slots-content');
    hide('slots-spinner');
    hide('slots-empty');
    document.getElementById('slots-list').innerHTML = '';
  }

  // ── Step 3: Client details ────────────────────────────────────────────────
  function updateVatHint(priceIncVAT) {
    var vatRate = (state.config && state.config.vatRate) || 20;
    var hint = document.getElementById('price-vat-hint');
    if (priceIncVAT > 0) {
      var priceExVAT = priceIncVAT / (1 + vatRate / 100);
      var vatAmount  = priceIncVAT - priceExVAT;
      hint.textContent =
        'Ex VAT: £' + priceExVAT.toFixed(2) + '  ·  VAT (' + vatRate + '%): £' + vatAmount.toFixed(2);
    } else {
      hint.textContent = '';
    }
  }

  function submitBooking() {
    var errDiv = document.getElementById('details-error');
    var btn    = document.getElementById('submit-btn');
    hide('details-error');

    var nameVal    = document.getElementById('f-name').value.trim();
    var emailVal   = document.getElementById('f-email').value.trim();
    var phoneVal   = document.getElementById('f-phone').value.trim();
    var addressVal = document.getElementById('f-address').value.trim();
    var priceVal   = document.getElementById('f-price').value.trim();
    var bedroomsVal = document.getElementById('f-bedrooms').value;
    var notesVal   = document.getElementById('f-notes').value.trim();

    var errors = [];
    if (!nameVal)    errors.push('Client name is required.');
    if (!emailVal || !emailVal.includes('@')) errors.push('A valid email address is required.');
    if (!phoneVal)   errors.push('Telephone number is required.');
    if (!addressVal) errors.push('Property address is required.');
    if (!priceVal)   errors.push('Price is required.');
    if (!state.selectedType || !state.selectedDate || !state.selectedTime) {
      errors.push('Please select a date and time.');
    }

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
    formData.append('clientName',    nameVal);
    formData.append('clientEmail',   emailVal);
    formData.append('clientPhone',   phoneVal);
    formData.append('clientAddress', addressVal);
    formData.append('bedrooms',      bedroomsVal);
    formData.append('priceIncVAT',   priceVal);
    formData.append('notes',         notesVal);

    fetch(APPS_SCRIPT_URL, { method: 'POST', body: formData })
      .then(function (r) { return r.json(); })
      .then(function (result) {
        if (result.success) {
          showSuccess(result, {
            name:     nameVal,
            email:    emailVal,
            phone:    phoneVal,
            address:  addressVal,
            bedrooms: bedroomsVal,
            price:    priceVal,
          });
        } else {
          errDiv.textContent = result.error || 'Something went wrong. Please try again.';
          show('details-error');
          resetSubmitBtn(btn);
        }
      })
      .catch(function () {
        errDiv.textContent = 'Network error. Please check your connection and try again.';
        show('details-error');
        resetSubmitBtn(btn);
      });
  }

  function resetSubmitBtn(btn) {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Confirm Booking';
  }

  // ── Step 4: Success ───────────────────────────────────────────────────────
  function showSuccess(result, client) {
    document.getElementById('s-ref').textContent      = result.ref || '—';
    document.getElementById('s-type').textContent     = state.selectedType.name;
    document.getElementById('s-datetime').textContent =
      formatDate(state.selectedDate) + ' at ' + state.selectedLabel;
    document.getElementById('s-client').textContent   = client.name;
    document.getElementById('s-email').textContent    = client.email;
    document.getElementById('s-phone').textContent    = client.phone  || '—';
    document.getElementById('s-address').textContent  = client.address || '—';

    // Bedrooms — hide row if not selected
    var bedLabel = document.getElementById('s-bed-label');
    var bedVal   = document.getElementById('s-bedrooms');
    if (client.bedrooms) {
      bedVal.textContent       = client.bedrooms;
      bedLabel.style.display   = '';
      bedVal.style.display     = '';
    } else {
      bedLabel.style.display   = 'none';
      bedVal.style.display     = 'none';
    }

    // Price
    var priceNum = parseFloat(client.price) || 0;
    document.getElementById('s-price').textContent = '£' + priceNum.toFixed(2);

    goTo('success');
    document.getElementById('details-form').reset();
    document.getElementById('price-vat-hint').textContent = '';
  }

  // ── Navigation ────────────────────────────────────────────────────────────
  var STEPS = ['type', 'datetime', 'details', 'success'];

  function goTo(step) {
    STEPS.forEach(function (s) {
      var el = document.getElementById('step-' + s);
      if (el) el.classList.add('d-none');
    });
    var target = document.getElementById('step-' + step);
    if (target) {
      target.classList.remove('d-none');
      target.classList.add('step-section');
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function reset() {
    state.selectedType  = null;
    state.selectedDate  = '';
    state.selectedTime  = '';
    state.selectedLabel = '';
    if (state.picker) {
      state.picker.clear();
      state.picker.destroy();
      state.picker = null;
    }
    resetSlots();
    document.getElementById('details-form').reset();
    document.getElementById('price-vat-hint').textContent = '';
    hide('details-error');
    goTo('type');
  }

  // ── Utilities ─────────────────────────────────────────────────────────────
  function show(id) { var el = document.getElementById(id); if (el) el.classList.remove('d-none'); }
  function hide(id) { var el = document.getElementById(id); if (el) el.classList.add('d-none'); }

  function showError(msg) {
    document.getElementById('error-msg').innerHTML = msg;
    show('error-screen');
  }

  function esc(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function formatDate(dateStr) {
    if (!dateStr) return '';
    var parts = dateStr.split('-');
    var d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
    return d.toLocaleDateString('en-GB', {
      weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
    });
  }

  // ── Public API (called from HTML onclick attributes) ──────────────────────
  window.App = { goTo: goTo, reset: reset };

})();
