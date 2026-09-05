/* ============================================================
   Furusato Japanese Restaurant - Contact Page JavaScript
   Handles: reservation form validation, submission, error handling
   FIXED: CSRF token support, rate limiting UI, phone validation
   NEW: Duplicate reservation popup with edit option
   ============================================================ */

(function () {
  "use strict";

  /* ----------------------------------------------------------
     Configuration
     ---------------------------------------------------------- */
  var API_BASE = (function() {
    var path = window.location.pathname;
    var folder = path.substring(0, path.lastIndexOf('/'));
    if (folder === '' || folder === '/') {
      return '/api/reservations.php';
    }
    return folder + '/api/reservations.php';
  })();
  
  var PHONE_PATTERN = /^[\+\d][\d\s\-\(\)]{8,20}$/;
  var EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  var MAX_SPECIAL_REQUESTS = 500;
  var pendingEditId = null;

  /* ----------------------------------------------------------
     State
     ---------------------------------------------------------- */
  var isSubmitting = false;
  var csrfToken = null;
  var rateLimitUntil = null;

  /* ----------------------------------------------------------
     DOM References
     ---------------------------------------------------------- */
  var formEl = null;
  var successEl = null;
  var nameInput = null;
  var emailInput = null;
  var phoneInput = null;
  var dateInput = null;
  var timeInput = null;
  var guestsInput = null;
  var specialRequestsInput = null;
  var honeypotInput = null;
  var csrfInput = null;
  var charCounterEl = null;
  var submitBtn = null;

  /* ----------------------------------------------------------
     Duplicate Modal Creation
     ---------------------------------------------------------- */
  function createDuplicateModal() {
    // Check if modal already exists
    if (document.getElementById('duplicate-modal')) return;
    
    var modalHtml = `
      <div id="duplicate-modal" class="duplicate-modal" style="display:none;">
        <div class="duplicate-modal-content">
          <div class="duplicate-modal-header">
            <i class="fas fa-calendar-alt"></i>
            <h3>Reservation Already Exists</h3>
          </div>
          <div class="duplicate-modal-body">
            <p>A reservation with these details was submitted recently:</p>
            <div id="duplicate-details" class="duplicate-details"></div>
            <p class="duplicate-question">Would you like to edit the existing reservation?</p>
          </div>
          <div class="duplicate-modal-footer">
            <button id="duplicate-edit-btn" class="btn-edit">
              <i class="fas fa-edit"></i> Edit Existing
            </button>
            <button id="duplicate-cancel-btn" class="btn-cancel">
              <i class="fas fa-times"></i> Cancel
            </button>
            <button id="duplicate-new-btn" class="btn-new">
              <i class="fas fa-plus"></i> Create New
            </button>
          </div>
        </div>
      </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Add styles
    if (!document.querySelector('#duplicate-modal-styles')) {
      var styles = document.createElement('style');
      styles.id = 'duplicate-modal-styles';
      styles.textContent = `
        .duplicate-modal {
          position: fixed;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          background: rgba(0,0,0,0.8);
          z-index: 2000;
          display: flex;
          align-items: center;
          justify-content: center;
          backdrop-filter: blur(4px);
        }
        .duplicate-modal-content {
          background: #fff;
          max-width: 500px;
          width: 90%;
          border-radius: 20px;
          overflow: hidden;
          box-shadow: 0 25px 50px rgba(0,0,0,0.3);
          animation: modalSlideIn 0.3s ease;
        }
        @keyframes modalSlideIn {
          from { transform: translateY(-30px); opacity: 0; }
          to { transform: translateY(0); opacity: 1; }
        }
        .duplicate-modal-header {
          background: linear-gradient(135deg, #9a7520 0%, #c9a03d 100%);
          padding: 20px 24px;
          color: #fff;
          display: flex;
          align-items: center;
          gap: 12px;
        }
        .duplicate-modal-header i {
          font-size: 24px;
        }
        .duplicate-modal-header h3 {
          margin: 0;
          font-family: 'Cormorant Garamond', serif;
          font-size: 1.5rem;
          font-weight: 600;
        }
        .duplicate-modal-body {
          padding: 24px;
        }
        .duplicate-modal-body p {
          margin: 0 0 12px 0;
          color: #333;
        }
        .duplicate-details {
          background: #f5f2ec;
          border-radius: 12px;
          padding: 16px;
          margin: 12px 0;
          border-left: 4px solid #c9a03d;
        }
        .duplicate-details p {
          margin: 6px 0;
          font-size: 0.9rem;
        }
        .duplicate-details strong {
          color: #9a7520;
        }
        .duplicate-question {
          font-weight: 600;
          margin-top: 16px !important;
          color: #0d1b2a !important;
        }
        .duplicate-modal-footer {
          padding: 16px 24px 24px;
          display: flex;
          gap: 12px;
          justify-content: flex-end;
          flex-wrap: wrap;
        }
        .btn-edit, .btn-cancel, .btn-new {
          padding: 10px 20px;
          border-radius: 50px;
          font-weight: 600;
          cursor: pointer;
          transition: all 0.3s ease;
          border: none;
          font-size: 0.85rem;
        }
        .btn-edit {
          background: linear-gradient(135deg, #9a7520 0%, #c9a03d 100%);
          color: #0d1b2a;
        }
        .btn-edit:hover {
          transform: translateY(-2px);
          box-shadow: 0 4px 12px rgba(156, 117, 32, 0.3);
        }
        .btn-new {
          background: #0d1b2a;
          color: #fff;
        }
        .btn-new:hover {
          background: #162336;
          transform: translateY(-2px);
        }
        .btn-cancel {
          background: #e0e0e0;
          color: #333;
        }
        .btn-cancel:hover {
          background: #ccc;
          transform: translateY(-2px);
        }
        @media (max-width: 480px) {
          .duplicate-modal-footer {
            flex-direction: column;
          }
          .btn-edit, .btn-cancel, .btn-new {
            width: 100%;
            text-align: center;
          }
        }
      `;
      document.head.appendChild(styles);
    }
  }

  function showDuplicateModal(existingData, existingId, editToken) {
    createDuplicateModal();
    pendingEditId = existingId;
    
    var modal = document.getElementById('duplicate-modal');
    var detailsDiv = document.getElementById('duplicate-details');
    
    if (detailsDiv) {
      detailsDiv.innerHTML = `
        <p><strong>📅 Date:</strong> ${escapeHtml(existingData.date || '')}</p>
        <p><strong>⏰ Time:</strong> ${escapeHtml(existingData.time || '')}</p>
        <p><strong>👥 Guests:</strong> ${escapeHtml(existingData.guests || '')}</p>
        <p><strong>📝 Special Requests:</strong> ${escapeHtml(existingData.special_requests || 'None')}</p>
      `;
    }
    
    modal.style.display = 'flex';
    
    // Setup event listeners
    var editBtn = document.getElementById('duplicate-edit-btn');
    var cancelBtn = document.getElementById('duplicate-cancel-btn');
    var newBtn = document.getElementById('duplicate-new-btn');
    
    var newEditBtn = editBtn.cloneNode(true);
    var newCancelBtn = cancelBtn.cloneNode(true);
    var newNewBtn = newBtn.cloneNode(true);
    
    editBtn.parentNode.replaceChild(newEditBtn, editBtn);
    cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
    newBtn.parentNode.replaceChild(newNewBtn, newBtn);
    
    newEditBtn.addEventListener('click', function() {
      modal.style.display = 'none';
      loadReservationForEdit(existingId, existingData, editToken);
    });
    
    newCancelBtn.addEventListener('click', function() {
      modal.style.display = 'none';
      pendingEditId = null;
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-calendar-check"></i> Reserve My Table';
      }
    });
    
    newNewBtn.addEventListener('click', function() {
      modal.style.display = 'none';
      pendingEditId = null;
      // Submit the new reservation
      submitNewReservation();
    });
  }
  
  function loadReservationForEdit(id, data, editToken) {
    // Populate form with existing data
    if (nameInput) nameInput.value = data.name || '';
    if (emailInput) emailInput.value = data.email || '';
    if (phoneInput) phoneInput.value = data.phone || '';
    if (dateInput) dateInput.value = data.date || '';
    if (timeInput) timeInput.value = data.time || '';
    if (guestsInput) guestsInput.value = data.guests || '';
    if (specialRequestsInput) specialRequestsInput.value = data.special_requests || '';
    
    // Update character counter
    if (specialRequestsInput && charCounterEl) {
      updateCharCounter();
    }
    
    // Scroll to form
    if (formEl) {
      formEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    // Change submit button to "Update Reservation"
    if (submitBtn) {
      submitBtn.innerHTML = '<i class="fas fa-save"></i> Update Reservation';
      submitBtn.style.background = 'linear-gradient(135deg, #2c3e50 0%, #3498db 100%)';
    }
    
    // Store that we're in edit mode
    window.isEditing = true;
    window.editingId = id;
    window.editingToken = editToken || null;
  }
  
  async function submitUpdateReservation(id, payload) {
    try {
      var response = await fetch(API_BASE, {
        method: "PUT",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json",
          "X-Requested-With": "XMLHttpRequest",
          "X-CSRF-Token": csrfToken
        },
        body: JSON.stringify({ id: id, token: window.editingToken || "", ...payload })
      });
      
      var result = await response.json();
      
      if (response.ok && result.success) {
        showSuccess({ message: 'Reservation updated successfully!' });
        // Reset edit mode
        window.isEditing = false;
        window.editingId = null;
        window.editingToken = null;
        if (submitBtn) {
          submitBtn.innerHTML = '<i class="fas fa-calendar-check"></i> Reserve My Table';
          submitBtn.style.background = '';
        }
      } else {
        if (response.status === 403 && result.csrf_token) {
          updateCsrfToken(result.csrf_token);
        }
        showGeneralError(result.error || 'Failed to update reservation. Please try again.');
      }
    } catch (err) {
      console.error('Update error:', err);
      showGeneralError('Network error. Please check your connection.');
    } finally {
      isSubmitting = false;
      if (submitBtn) {
        submitBtn.disabled = false;
        if (!window.isEditing) {
          submitBtn.innerHTML = '<i class="fas fa-calendar-check"></i> Reserve My Table';
        }
      }
    }
  }
  
  async function submitNewReservation() {
    // Get fresh CSRF token
    csrfToken = getCsrfToken();
    
    if (!csrfToken) {
      showGeneralError("Security token missing. Please refresh the page and try again.");
      setTimeout(function() { location.reload(); }, 2000);
      return;
    }
    
    var payload = {
      name: nameInput.value.trim(),
      email: emailInput.value.trim(),
      phone: phoneInput.value.trim(),
      date: dateInput.value,
      time: timeInput.value,
      guests: parseInt(guestsInput.value, 10),
      special_requests: specialRequestsInput ? specialRequestsInput.value.trim().substring(0, MAX_SPECIAL_REQUESTS) : "",
      website: honeypotInput ? honeypotInput.value : "",
      csrf_token: csrfToken
    };
    
    try {
      var response = await fetch(API_BASE, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json",
          "X-Requested-With": "XMLHttpRequest",
          "X-CSRF-Token": csrfToken
        },
        body: JSON.stringify(payload)
      });
      
      var result = await response.json();
      
      if (response.status === 409 && result.duplicate_detected) {
        // Show duplicate modal
        showDuplicateModal(result.existing_data, result.existing_id, result.edit_token || null);
        return;
      }
      
      if (response.status === 429) {
        var retryAfter = result.data?.retry_after || 600;
        var retryMinutes = result.data?.retry_minutes || Math.ceil(retryAfter / 60);
        var rateLimitExpiry = Date.now() + (retryAfter * 1000);
        localStorage.setItem('furusato_rate_limit_until', rateLimitExpiry);
        showRateLimitOverlay(retryAfter, retryMinutes);
        if (formEl) formEl.style.display = "none";
      } else if (response.status === 403) {
        if (result.csrf_token) {
          updateCsrfToken(result.csrf_token);
        }
        showGeneralError(result.error || "Please refresh the page and try again.");
      } else if (response.status === 422) {
        showGeneralError(result.error || "Please check your input and try again.");
      } else if (response.ok && result.success) {
        showSuccess(result);
        if (result.csrf_token) {
          updateCsrfToken(result.csrf_token);
        }
        localStorage.removeItem('furusato_rate_limit_until');
      } else {
        showGeneralError(result.error || "Something went wrong. Please try again.");
      }
    } catch (err) {
      console.error("Submission error:", err);
      showGeneralError("Network error. Please check your connection and try again.");
    } finally {
      isSubmitting = false;
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-calendar-check"></i> Reserve My Table';
        submitBtn.style.background = '';
      }
    }
  }

  /* ----------------------------------------------------------
     Helper Functions
     ---------------------------------------------------------- */
  function getCsrfToken() {
    var metaToken = document.querySelector('meta[name="csrf-token"]');
    if (metaToken && metaToken.getAttribute('content')) {
      return metaToken.getAttribute('content');
    }
    if (csrfInput && csrfInput.value) {
      return csrfInput.value;
    }
    return null;
  }

  function updateCsrfToken(newToken) {
    if (!newToken) return;
    csrfToken = newToken;
    var metaToken = document.querySelector('meta[name="csrf-token"]');
    if (metaToken) {
      metaToken.setAttribute('content', newToken);
    }
    if (csrfInput) {
      csrfInput.value = newToken;
    }
  }

  function isValidPhoneDigitCount(phone) {
    var digitsOnly = phone.replace(/[^0-9]/g, '');
    return digitsOnly.length >= 8 && digitsOnly.length <= 15;
  }

  function formatPhoneNumber() {
    if (!phoneInput) return;
    var raw = phoneInput.value;
    var formatted = raw.replace(/[^0-9+\s\-()]/g, '');
    if (formatted !== raw) {
      var cursorPos = phoneInput.selectionStart;
      phoneInput.value = formatted;
      try {
        phoneInput.setSelectionRange(cursorPos, cursorPos);
      } catch (e) {}
    }
  }

  function showRateLimitOverlay(retryAfterSeconds, retryMinutes) {
    var overlay = document.getElementById('rate-limit-overlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.id = 'rate-limit-overlay';
      overlay.className = 'rate-limit-overlay';
      overlay.innerHTML = '<div class="rate-limit-modal">' +
        '<i class="fas fa-clock"></i>' +
        '<h3>Please Wait</h3>' +
        '<p>You\'ve made several reservation attempts. Please wait <span id="countdown-timer" class="countdown">5:00</span> before trying again.</p>' +
        '<button class="rate-limit-btn" id="rate-limit-refresh-btn">OK, Refresh Page</button>' +
        '</div>';
      document.body.appendChild(overlay);
      
      if (!document.querySelector('#rate-limit-styles')) {
        var styles = document.createElement('style');
        styles.id = 'rate-limit-styles';
        styles.textContent = '.rate-limit-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:1000;display:none;align-items:center;justify-content:center;backdrop-filter:blur(8px)}.rate-limit-modal{background:#fff;max-width:450px;width:90%;padding:40px 32px;border-radius:24px;text-align:center;box-shadow:0 20px 70px rgba(14,12,10,0.16);border-top:4px solid #c9a03d}.rate-limit-modal i{font-size:3.5rem;color:#c9a03d;margin-bottom:20px}.rate-limit-modal h3{font-family:"Cormorant Garamond",serif;font-size:1.8rem;margin-bottom:12px}.rate-limit-modal p{color:rgba(14,12,10,0.8);margin-bottom:24px;font-size:0.9rem}.rate-limit-modal .countdown{font-size:1.2rem;font-weight:700;color:#9a7520}.rate-limit-btn{background:linear-gradient(135deg,#9a7520 0%,#c9a03d 40%,#e8d08a 60%,#c9a03d 80%,#9a7520 100%);border:none;padding:12px 28px;border-radius:50px;font-weight:600;cursor:pointer;margin-top:16px;color:#0d1b2a}';
        document.head.appendChild(styles);
      }
      overlay = document.getElementById('rate-limit-overlay');
      var refreshBtn = document.getElementById('rate-limit-refresh-btn');
      if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
          location.reload();
        });
      }
    }
    
    overlay.style.display = 'flex';
    var timerSpan = document.getElementById('countdown-timer');
    if (timerSpan && retryAfterSeconds) {
      var displayMinutes = retryMinutes || Math.ceil(retryAfterSeconds / 60);
      var displaySeconds = retryAfterSeconds % 60;
      timerSpan.textContent = displayMinutes + ':' + (displaySeconds < 10 ? '0' + displaySeconds : displaySeconds);
      
      var remaining = retryAfterSeconds;
      var interval = setInterval(function() {
        remaining--;
        if (remaining <= 0) {
          clearInterval(interval);
          overlay.style.display = 'none';
          if (formEl) formEl.style.display = 'block';
          location.reload();
        } else {
          var mins = Math.floor(remaining / 60);
          var secs = remaining % 60;
          timerSpan.textContent = mins + ':' + (secs < 10 ? '0' + secs : secs);
        }
      }, 1000);
    }
  }

  function init() {
    formEl = document.getElementById("reservation-form");
    successEl = document.getElementById("reservation-success");

    if (!formEl) {
      console.error("Reservation form #reservation-form not found");
      return;
    }

    nameInput = document.getElementById("reservation-name");
    emailInput = document.getElementById("reservation-email");
    phoneInput = document.getElementById("reservation-phone");
    dateInput = document.getElementById("reservation-date");
    timeInput = document.getElementById("reservation-time");
    guestsInput = document.getElementById("reservation-guests");
    specialRequestsInput = document.getElementById("reservation-requests");
    honeypotInput = formEl.querySelector('input[name="website"]');
    csrfInput = document.getElementById("csrf_token_input");
    submitBtn = formEl.querySelector('.btn-reserve');

    setInputConstraints();
    populateGuestsDropdown();
    bindFormEvents();
    insertCharCounter();
    addPhoneHint();

    csrfToken = getCsrfToken();

    // Token warm-up: when this page is served from the LiteSpeed
    // page cache, the embedded CSRF token may belong to a previous
    // session (the cache does not start a PHP session), which makes
    // the FIRST reservation submission fail. Fetch a session-bound
    // token from the always-uncached API endpoint instead so the
    // first attempt succeeds. If the warm-up fails we simply keep
    // the page-embedded token and the existing 403 recovery path
    // still works.
    try {
      fetch(API_BASE + "?action=csrf", {
        credentials: "same-origin",
        headers: { "Accept": "application/json" }
      })
        .then(function(r) { return r.ok ? r.json() : null; })
        .then(function(data) {
          if (data && data.success && data.csrf_token) {
            updateCsrfToken(data.csrf_token);
          }
        })
        .catch(function() { /* keep the page-embedded token */ });
    } catch (e) { /* non-fatal */ }

    var storedRateLimit = localStorage.getItem('furusato_rate_limit_until');
    if (storedRateLimit && parseInt(storedRateLimit) > Date.now()) {
      var remainingSeconds = Math.ceil((parseInt(storedRateLimit) - Date.now()) / 1000);
      var remainingMinutes = Math.ceil(remainingSeconds / 60);
      if (remainingSeconds > 0) {
        showRateLimitOverlay(remainingSeconds, remainingMinutes);
        if (formEl) formEl.style.display = 'none';
        if (submitBtn) submitBtn.disabled = true;
      } else {
        localStorage.removeItem('furusato_rate_limit_until');
      }
    }
  }
  
  function addPhoneHint() {
    if (!phoneInput) return;
    var parent = phoneInput.parentElement;
    if (!parent) return;
    
    var existingHint = parent.querySelector('.phone-hint');
    if (existingHint) return;
    
    var hint = document.createElement("small");
    hint.className = "f-note phone-hint";
    hint.innerHTML = '<i class="fas fa-info-circle"></i> Include your country code (e.g., +254722488706 for Kenya)';
    parent.appendChild(hint);
  }

  function populateGuestsDropdown() {
    if (!guestsInput) return;
    while (guestsInput.options.length > 1) {
      guestsInput.remove(1);
    }
    for (var i = 1; i <= 20; i++) {
      var opt = document.createElement("option");
      opt.value = i;
      opt.textContent = i + (i === 1 ? " Guest" : " Guests");
      guestsInput.appendChild(opt);
    }
  }

  function setInputConstraints() {
    if (dateInput) {
      var tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);
      var tomorrowStr = formatDateISO(tomorrow);
      var maxDate = new Date(tomorrow);
      maxDate.setFullYear(maxDate.getFullYear() + 1);
      var maxDateStr = formatDateISO(maxDate);
      dateInput.setAttribute("min", tomorrowStr);
      dateInput.setAttribute("max", maxDateStr);
      if (!dateInput.value) {
        dateInput.value = tomorrowStr;
      }
    }

    if (timeInput) {
      timeInput.setAttribute("min", "12:00");
      timeInput.setAttribute("max", "21:00");
      if (!timeInput.value) {
        timeInput.value = "19:00";
      }
    }

    if (specialRequestsInput) {
      specialRequestsInput.setAttribute("maxlength", MAX_SPECIAL_REQUESTS);
    }
  }

  function insertCharCounter() {
    if (!specialRequestsInput) return;
    var parent = specialRequestsInput.parentElement;
    if (!parent) return;

    charCounterEl = parent.querySelector(".char-counter");
    if (charCounterEl) return;

    charCounterEl = document.createElement("div");
    charCounterEl.className = "char-counter";
    updateCharCounter();
    parent.appendChild(charCounterEl);
  }

  function updateCharCounter() {
    if (!charCounterEl || !specialRequestsInput) return;
    var len = specialRequestsInput.value.length;
    charCounterEl.textContent = len + " / " + MAX_SPECIAL_REQUESTS;
    if (len > MAX_SPECIAL_REQUESTS) {
      charCounterEl.style.color = "#C0392B";
    } else if (len > MAX_SPECIAL_REQUESTS * 0.9) {
      charCounterEl.style.color = "#e67e22";
    } else {
      charCounterEl.style.color = "var(--ink-40)";
    }
  }

  function bindFormEvents() {
    if (nameInput) nameInput.addEventListener("blur", function() { validateField("name"); });
    if (emailInput) emailInput.addEventListener("blur", function() { validateField("email"); });
    if (phoneInput) {
      phoneInput.addEventListener("blur", function() { validateField("phone"); });
      phoneInput.addEventListener("input", formatPhoneNumber);
    }
    if (dateInput) dateInput.addEventListener("blur", function() { validateField("date"); });
    if (timeInput) timeInput.addEventListener("blur", function() { validateField("time"); });
    if (guestsInput) guestsInput.addEventListener("change", function() { validateField("guests"); });

    if (specialRequestsInput) {
      specialRequestsInput.addEventListener("input", function() {
        updateCharCounter();
        validateField("special_requests");
      });
    }

    formEl.addEventListener("submit", handleSubmit);
  }

  var validationRules = {
    name: {
      required: true,
      validate: function(val) {
        if (val.length < 2) return "Full name is required (minimum 2 characters)";
        if (val.length > 100) return "Name must not exceed 100 characters";
        return "";
      }
    },
    email: {
      required: false,
      validate: function(val) {
        if (val && !EMAIL_PATTERN.test(val)) {
          return "Please enter a valid email address";
        }
        return "";
      }
    },
    phone: {
      required: true,
      validate: function(val) {
        if (!PHONE_PATTERN.test(val)) {
          return "Please enter a valid phone number with country code (e.g., +254722488706 for Kenya)";
        }
        if (!isValidPhoneDigitCount(val)) {
          return "Phone number must have between 8 and 15 digits (include your country code)";
        }
        return "";
      }
    },
    date: {
      required: true,
      validate: function(val) {
        if (!val) return "Please select a date";
        var selected = new Date(val + "T00:00:00");
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        var tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);
        if (selected < tomorrow) return "Date cannot be in the past";
        var maxDate = new Date(today);
        maxDate.setFullYear(maxDate.getFullYear() + 1);
        if (selected > maxDate) return "Date cannot be more than 1 year from now";
        return "";
      }
    },
    time: {
      required: true,
      validate: function(val) {
        if (!val) return "Please select a time";
        var parts = val.split(":");
        var hours = parseInt(parts[0], 10);
        var minutes = parseInt(parts[1], 10);
        if (hours < 12) return "Time must be 12:00 PM or later";
        if (hours > 21) return "Last reservation time is 9:00 PM";
        if (hours === 21 && minutes > 0) return "Last reservation time is 9:00 PM";
        return "";
      }
    },
    guests: {
      required: true,
      validate: function(val) {
        var num = parseInt(val, 10);
        if (isNaN(num) || num < 1) return "Please select number of guests";
        if (num > 50) return "Maximum 50 guests per reservation. For larger parties, please call us directly.";
        return "";
      }
    },
    special_requests: {
      required: false,
      validate: function(val) {
        if (val.length > MAX_SPECIAL_REQUESTS) {
          return "Special requests must not exceed " + MAX_SPECIAL_REQUESTS + " characters";
        }
        return "";
      }
    }
  };

  function validateField(fieldName) {
    var input = getFieldInput(fieldName);
    if (!input) return true;

    var val = input.value.trim();
    var rule = validationRules[fieldName];
    if (!rule) return true;

    if (rule.required && !val) {
      showFieldError(fieldName, "This field is required");
      return false;
    }

    if (!rule.required && !val) {
      clearFieldError(fieldName);
      return true;
    }

    var error = rule.validate(val);
    if (error) {
      showFieldError(fieldName, error);
      return false;
    }

    clearFieldError(fieldName);
    return true;
  }

  function validateAll() {
    var allValid = true;
    var fields = ["name", "email", "phone", "date", "time", "guests"];

    for (var i = 0; i < fields.length; i++) {
      if (!validateField(fields[i])) {
        allValid = false;
      }
    }
    validateField("special_requests");
    
    return allValid;
  }

  function getFieldInput(fieldName) {
    switch (fieldName) {
      case "name": return nameInput;
      case "email": return emailInput;
      case "phone": return phoneInput;
      case "date": return dateInput;
      case "time": return timeInput;
      case "guests": return guestsInput;
      case "special_requests": return specialRequestsInput;
      default: return null;
    }
  }

  function showFieldError(fieldName, message) {
    var input = getFieldInput(fieldName);
    if (!input) return;

    input.classList.add("invalid");
    input.classList.remove("valid");

    var errorId = fieldName + "-error";
    var errorEl = document.getElementById(errorId);
    
    if (!errorEl) {
      var parent = input.parentElement;
      errorEl = document.createElement("span");
      errorEl.id = errorId;
      errorEl.className = "f-error";
      parent.appendChild(errorEl);
    }
    
    errorEl.textContent = message;
    errorEl.style.display = "block";
  }

  function clearFieldError(fieldName) {
    var input = getFieldInput(fieldName);
    if (!input) return;
    input.classList.remove("invalid");
    input.classList.add("valid");

    var errorId = fieldName + "-error";
    var errorEl = document.getElementById(errorId);
    if (errorEl) {
      errorEl.textContent = "";
      errorEl.style.display = "none";
    }
  }

  function checkHoneypot() {
    if (!honeypotInput) return true;
    return honeypotInput.value === "";
  }

  function showGeneralError(message) {
    var netError = document.getElementById("net-error");
    var netErrorMsg = document.getElementById("net-error-msg");
    
    if (netError && netErrorMsg) {
      netErrorMsg.textContent = message;
      netError.style.display = "flex";
      netError.scrollIntoView({ behavior: "smooth", block: "center" });
      
      setTimeout(function() {
        if (netError) netError.style.display = "none";
      }, 8000);
    } else {
      alert(message);
    }
  }

  function handleSubmit(e) {
    e.preventDefault();

    if (isSubmitting) return;

    if (!checkHoneypot()) {
      if (successEl) {
        formEl.style.display = "none";
        successEl.style.display = "block";
      }
      return;
    }

    if (!validateAll()) {
      var firstInvalid = formEl.querySelector(".invalid");
      if (firstInvalid) {
        firstInvalid.scrollIntoView({ behavior: "smooth", block: "center" });
        firstInvalid.focus();
      }
      return;
    }

    isSubmitting = true;
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';
    }
    
    // Check if we're in edit mode
    if (window.isEditing && window.editingId) {
      var updatePayload = {
        name: nameInput.value.trim(),
        email: emailInput.value.trim(),
        phone: phoneInput.value.trim(),
        date: dateInput.value,
        time: timeInput.value,
        guests: parseInt(guestsInput.value, 10),
        special_requests: specialRequestsInput ? specialRequestsInput.value.trim().substring(0, MAX_SPECIAL_REQUESTS) : "",
      };
      submitUpdateReservation(window.editingId, updatePayload);
    } else {
      submitNewReservation();
    }
  }

  function showSuccess(data) {
    if (formEl) {
      formEl.style.display = "none";
    }

    if (successEl) {
      var emailValue = emailInput ? emailInput.value.trim() : '';
      var hasEmail = emailValue !== '';
      var phoneValue = phoneInput ? phoneInput.value.trim() : '';
      
      var successBody = successEl.querySelector(".success-body");
      
      if (successBody) {
        if (hasEmail) {
          successBody.innerHTML = 'Thank you for choosing Furusato.<br>A confirmation email has been sent to <strong>' + escapeHtml(emailValue) + '</strong>.<br>We will confirm your table within 24 hours.<br><br>Questions? Call us: <a href="tel:0722488706">0722 488 706</a>';
        } else {
          successBody.innerHTML = 'Thank you for choosing Furusato.<br>We will contact you via <strong>' + escapeHtml(phoneValue) + '</strong> to confirm your table within 24 hours.<br><br>Questions? Call us: <a href="tel:0722488706">0722 488 706</a>';
        }
      }
      
      successEl.style.display = "block";
      successEl.scrollIntoView({ behavior: "smooth", block: "center" });
    }

    var newResBtn = document.getElementById("btn-new-reservation");
    if (newResBtn) {
      var newBtn = newResBtn.cloneNode(true);
      newResBtn.parentNode.replaceChild(newBtn, newResBtn);
      
      newBtn.addEventListener("click", function() {
        location.reload();
      });
    }
  }

  function formatDateISO(date) {
    var year = date.getFullYear();
    var month = String(date.getMonth() + 1).padStart(2, "0");
    var day = String(date.getDate()).padStart(2, "0");
    return year + "-" + month + "-" + day;
  }

  function escapeHtml(str) {
    if (!str) return "";
    var div = document.createElement("div");
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();