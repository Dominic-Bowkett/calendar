(function () {
    'use strict';

    var selectedDate = '';
    var selectedTime = '';

    // Initialize Flatpickr date picker
    flatpickr('#booking_date', {
        minDate: MIN_DATE,
        maxDate: MAX_DATE,
        disableMobile: false,
        onChange: function (selectedDates, dateStr) {
            selectedDate = dateStr;
            selectedTime = '';
            document.getElementById('selected_time').value = '';
            loadSlots(dateStr);
            hideDetailsStep();
        }
    });

    function loadSlots(date) {
        var slotsContainer = document.getElementById('slots-container');
        var slotsGrid      = document.getElementById('slots-grid');
        var slotsLoading   = document.getElementById('slots-loading');
        var slotsEmpty     = document.getElementById('slots-empty');

        slotsContainer.classList.add('d-none');
        slotsEmpty.classList.add('d-none');
        slotsLoading.classList.remove('d-none');
        slotsGrid.innerHTML = '';

        var url = API_URL + '?service_id=' + SERVICE_ID + '&date=' + encodeURIComponent(date);

        fetch(url)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                slotsLoading.classList.add('d-none');

                if (data.error || !data.slots || data.slots.length === 0) {
                    slotsEmpty.classList.remove('d-none');
                    return;
                }

                data.slots.forEach(function (slot) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'btn btn-outline-primary slot-btn';
                    btn.textContent = slot.label;
                    btn.dataset.time = slot.time;
                    btn.addEventListener('click', function () {
                        selectSlot(btn, slot);
                    });
                    slotsGrid.appendChild(btn);
                });

                slotsContainer.classList.remove('d-none');
            })
            .catch(function () {
                slotsLoading.classList.add('d-none');
                slotsEmpty.classList.remove('d-none');
                slotsEmpty.querySelector('p').textContent = 'Failed to load slots. Please try again.';
            });
    }

    function selectSlot(btn, slot) {
        // Deselect all
        document.querySelectorAll('.slot-btn').forEach(function (b) {
            b.classList.remove('selected', 'btn-primary');
            b.classList.add('btn-outline-primary');
        });
        // Select clicked
        btn.classList.add('selected', 'btn-primary');
        btn.classList.remove('btn-outline-primary');

        selectedTime = slot.time;
        document.getElementById('selected_time').value = slot.time;

        // Show details step
        document.getElementById('form_date').value = selectedDate;
        document.getElementById('form_time').value  = selectedTime;

        var displayEl = document.getElementById('selected-time-display');
        var d = new Date(selectedDate + 'T' + slot.time + ':00');
        displayEl.textContent = d.toLocaleDateString('en-GB', { weekday:'long', day:'numeric', month:'long', year:'numeric' })
                              + ' at ' + slot.label;

        showDetailsStep();
    }

    function showDetailsStep() {
        var step = document.getElementById('step-details');
        step.style.removeProperty('display');
        step.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function hideDetailsStep() {
        document.getElementById('step-details').style.setProperty('display', 'none', 'important');
    }

    // Back button
    document.getElementById('btn-back').addEventListener('click', function () {
        selectedTime = '';
        document.querySelectorAll('.slot-btn').forEach(function (b) {
            b.classList.remove('selected', 'btn-primary');
            b.classList.add('btn-outline-primary');
        });
        hideDetailsStep();
    });

    // Booking form submission
    document.getElementById('booking-form').addEventListener('submit', function (e) {
        e.preventDefault();

        var form      = this;
        var btn       = document.getElementById('btn-submit');
        var errorDiv  = document.getElementById('booking-error');

        errorDiv.classList.add('d-none');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Confirming…';

        var data = new FormData(form);

        fetch(BOOK_URL, {
            method: 'POST',
            body: data
        })
        .then(function (res) { return res.json(); })
        .then(function (result) {
            if (result.success) {
                window.location.href = CONFIRM_URL + '?id=' + result.booking_id;
            } else {
                errorDiv.textContent = result.error || 'Something went wrong. Please try again.';
                errorDiv.classList.remove('d-none');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Confirm Booking';
            }
        })
        .catch(function () {
            errorDiv.textContent = 'Network error. Please try again.';
            errorDiv.classList.remove('d-none');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Confirm Booking';
        });
    });
})();
