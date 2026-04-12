// ═══════════════════════════════════════════════════════════════════════════════
//  BOOKING APP — Google Apps Script Backend
//  ─────────────────────────────────────────
//  1. Go to script.google.com → New project
//  2. Paste this entire file, replacing any existing code
//  3. Edit the CONFIGURATION section below
//  4. Click Deploy → New deployment → Web app
//     • Execute as: Me
//     • Who has access: Anyone
//  5. Copy the web app URL and paste it into assets/js/config.js
// ═══════════════════════════════════════════════════════════════════════════════


// ─── CONFIGURATION ─────────────────────────────────────────────────────────────
// Edit this section freely. After changes: Deploy → Manage deployments → new version.
// ───────────────────────────────────────────────────────────────────────────────

var CONFIG = {

  // Your name — shown in confirmation emails
  ownerName: 'Your Name',

  // Time slot increment in minutes.
  // 15 = options every 15 mins (9:00, 9:15, 9:30 …)
  // 30 = options every 30 mins (9:00, 9:30, 10:00 …)
  slotIncrement: 15,

  // Working hours per day of week. Set to null for days you don't work.
  // 0 = Sunday, 1 = Monday, 2 = Tuesday, 3 = Wednesday,
  // 4 = Thursday, 5 = Friday, 6 = Saturday
  workingHours: {
    0: null,                                 // Sunday    — closed
    1: { start: '09:00', end: '17:00' },     // Monday
    2: { start: '09:00', end: '17:00' },     // Tuesday
    3: { start: '09:00', end: '17:00' },     // Wednesday
    4: { start: '09:00', end: '17:00' },     // Thursday
    5: { start: '09:00', end: '17:00' },     // Friday
    6: null,                                 // Saturday  — closed
  },

  // ── Appointment types ──────────────────────────────────────────────────────
  // Add, remove, or edit entries as needed.
  // duration: appointment length in minutes
  // color:    hex colour shown in the booking UI card header
  // calColor: Google Calendar event colour (see list below)
  //
  // Available calColor values:
  //   BLUE | CYAN | GREEN | GREY | MAUVE | ORANGE | PINK | TEAL | TURQUOISE | YELLOW | RED
  // ──────────────────────────────────────────────────────────────────────────
  appointmentTypes: [
    {
      id:          'epc',
      name:        'EPC Assessment',
      duration:    60,
      color:       '#1a73e8',
      calColor:    'BLUE',
      description: 'Energy Performance Certificate assessment',
    },
    {
      id:          'floorplan',
      name:        'Floor Plan',
      duration:    90,
      color:       '#188038',
      calColor:    'GREEN',
      description: 'Property floor plan measurement and drawing',
    },
    {
      id:          'epc_floorplan',
      name:        'EPC + Floor Plan',
      duration:    120,
      color:       '#6200ea',
      calColor:    'MAUVE',
      description: 'Combined EPC assessment and floor plan',
    },
    {
      id:          'consultation',
      name:        'Consultation',
      duration:    30,
      color:       '#e37400',
      calColor:    'YELLOW',
      description: 'General consultation',
    },
  ],

  // ── Confirmation email sent to the client ──────────────────────────────────
  // Available placeholders:
  //   {clientName}      — client's full name
  //   {appointmentName} — e.g. "EPC Assessment"
  //   {date}            — e.g. "Monday, 14 April 2026"
  //   {time}            — e.g. "10:00 AM"
  //   {duration}        — e.g. "60"  (minutes)
  //   {ownerName}       — your name from ownerName above
  //   {address}         — property address (if provided)
  //   {notes}           — any notes (if provided)
  // ──────────────────────────────────────────────────────────────────────────
  emailSubject: 'Booking Confirmed — {appointmentName} on {date}',

  emailBody: `
    <div style="font-family:Arial,Helvetica,sans-serif;max-width:580px;margin:0 auto;color:#222">
      <div style="background:#1a73e8;padding:24px 28px;border-radius:8px 8px 0 0">
        <h1 style="color:#fff;margin:0;font-size:22px">Booking Confirmed</h1>
      </div>
      <div style="background:#fff;padding:28px;border:1px solid #e0e0e0;border-top:none;border-radius:0 0 8px 8px">
        <p>Hi {clientName},</p>
        <p>Your appointment has been confirmed. Here are your details:</p>

        <table style="width:100%;border-collapse:collapse;margin:16px 0">
          <tr style="border-bottom:1px solid #f0f0f0">
            <td style="padding:10px 0;color:#666;width:140px;font-size:14px">Appointment</td>
            <td style="padding:10px 0;font-weight:600">{appointmentName}</td>
          </tr>
          <tr style="border-bottom:1px solid #f0f0f0">
            <td style="padding:10px 0;color:#666;font-size:14px">Date</td>
            <td style="padding:10px 0;font-weight:600">{date}</td>
          </tr>
          <tr style="border-bottom:1px solid #f0f0f0">
            <td style="padding:10px 0;color:#666;font-size:14px">Time</td>
            <td style="padding:10px 0;font-weight:600">{time}</td>
          </tr>
          <tr style="border-bottom:1px solid #f0f0f0">
            <td style="padding:10px 0;color:#666;font-size:14px">Duration</td>
            <td style="padding:10px 0">{duration} minutes</td>
          </tr>
          {addressRow}
        </table>

        {notesSection}

        <p style="margin-top:24px;color:#555;font-size:14px">
          If you need to reschedule or cancel, please get in touch as soon as possible.
        </p>

        <p style="color:#555;font-size:14px">
          Kind regards,<br>
          <strong>{ownerName}</strong>
        </p>
      </div>
    </div>
  `,

};

// ═══════════════════════════════════════════════════════════════════════════════
//  CORE CODE — no need to edit below this line
// ═══════════════════════════════════════════════════════════════════════════════


/** Handle GET requests: ?action=config  or  ?action=slots&typeId=X&date=YYYY-MM-DD */
function doGet(e) {
  var result;
  try {
    var action = (e.parameter && e.parameter.action) || '';
    if (action === 'config') {
      result = getPublicConfig();
    } else if (action === 'slots') {
      result = getAvailableSlots(e.parameter.typeId, e.parameter.date);
    } else {
      result = { error: 'Unknown action' };
    }
  } catch (err) {
    result = { error: err.message };
  }
  return jsonResponse(result);
}


/** Handle POST requests: create a booking */
function doPost(e) {
  var result;
  try {
    var p = e.parameter;
    result = createBooking({
      typeId:        p.typeId        || '',
      date:          p.date          || '',
      time:          p.time          || '',
      clientName:    p.clientName    || '',
      clientEmail:   p.clientEmail   || '',
      clientPhone:   p.clientPhone   || '',
      clientAddress: p.clientAddress || '',
      notes:         p.notes         || '',
    });
  } catch (err) {
    result = { success: false, error: err.message };
  }
  return jsonResponse(result);
}


/** Returns config safe to send to the frontend (no CalendarApp constants). */
function getPublicConfig() {
  return {
    ownerName:     CONFIG.ownerName,
    slotIncrement: CONFIG.slotIncrement,
    workingHours:  CONFIG.workingHours,
    appointmentTypes: CONFIG.appointmentTypes.map(function(t) {
      return { id: t.id, name: t.name, duration: t.duration, color: t.color, description: t.description };
    }),
  };
}


/** Returns available time slots for a given appointment type and date. */
function getAvailableSlots(typeId, dateStr) {
  var type = CONFIG.appointmentTypes.filter(function(t) { return t.id === typeId; })[0];
  if (!type) throw new Error('Unknown appointment type: ' + typeId);

  var parts     = dateStr.split('-');
  var year      = parseInt(parts[0]);
  var month     = parseInt(parts[1]) - 1;
  var day       = parseInt(parts[2]);
  var dayOfWeek = new Date(year, month, day).getDay();
  var hours     = CONFIG.workingHours[dayOfWeek];
  if (!hours) return [];

  var tz        = Session.getScriptTimeZone();
  var sParts    = hours.start.split(':');
  var eParts    = hours.end.split(':');
  var dayStart  = new Date(year, month, day, parseInt(sParts[0]), parseInt(sParts[1]), 0, 0);
  var dayEnd    = new Date(year, month, day, parseInt(eParts[0]), parseInt(eParts[1]), 0, 0);
  var now       = new Date();
  var stepMs    = CONFIG.slotIncrement * 60 * 1000;
  var durMs     = type.duration       * 60 * 1000;

  // Generate candidate slots
  var slots = [];
  var cursor = new Date(dayStart.getTime());
  while (true) {
    var slotEnd = new Date(cursor.getTime() + durMs);
    if (slotEnd > dayEnd) break;
    if (cursor > now) {
      slots.push({ start: cursor.getTime(), end: slotEnd.getTime() });
    }
    cursor = new Date(cursor.getTime() + stepMs);
  }
  if (slots.length === 0) return [];

  // Fetch all calendar events for the day
  var calendar    = getCalendar();
  var dayStartDt  = new Date(year, month, day, 0, 0, 0);
  var dayEndDt    = new Date(year, month, day, 23, 59, 59);
  var events      = calendar.getEvents(dayStartDt, dayEndDt);
  var busy        = events.map(function(ev) {
    return { start: ev.getStartTime().getTime(), end: ev.getEndTime().getTime() };
  });

  // Filter out slots that overlap any existing event
  var available = slots.filter(function(slot) {
    return !busy.some(function(b) {
      return slot.start < b.end && slot.end > b.start;
    });
  });

  return available.map(function(slot) {
    var d = new Date(slot.start);
    return {
      time:  Utilities.formatDate(d, tz, 'HH:mm'),
      label: Utilities.formatDate(d, tz, 'h:mm a'),
    };
  });
}


/** Creates a calendar event and sends a confirmation email. */
function createBooking(data) {
  var type = CONFIG.appointmentTypes.filter(function(t) { return t.id === data.typeId; })[0];
  if (!type) throw new Error('Unknown appointment type');
  if (!data.clientName)  throw new Error('Client name is required');
  if (!data.clientEmail) throw new Error('Client email is required');
  if (!data.date || !data.time) throw new Error('Date and time are required');

  var parts    = data.date.split('-');
  var tParts   = data.time.split(':');
  var startDt  = new Date(parseInt(parts[0]), parseInt(parts[1])-1, parseInt(parts[2]),
                          parseInt(tParts[0]), parseInt(tParts[1]), 0, 0);
  var endDt    = new Date(startDt.getTime() + type.duration * 60 * 1000);

  // Re-check availability (guard against double-booking)
  var calendar  = getCalendar();
  var conflicts = calendar.getEvents(startDt, endDt);
  if (conflicts.length > 0) {
    return { success: false, error: 'This slot is no longer available. Please choose another time.' };
  }

  // Build calendar event description
  var descLines = [
    'Client: '  + data.clientName,
    'Email: '   + data.clientEmail,
  ];
  if (data.clientPhone)   descLines.push('Phone: '   + data.clientPhone);
  if (data.clientAddress) descLines.push('Address: ' + data.clientAddress);
  if (data.notes)         descLines.push('Notes: '   + data.notes);

  // Create the event
  var event = calendar.createEvent(
    type.name + ' \u2014 ' + data.clientName,
    startDt,
    endDt,
    { description: descLines.join('\n') }
  );

  // Set event colour
  try {
    var colorMap = {
      BLUE: CalendarApp.EventColor.BLUE, CYAN: CalendarApp.EventColor.CYAN,
      GREEN: CalendarApp.EventColor.GREEN, GREY: CalendarApp.EventColor.GRAY,
      MAUVE: CalendarApp.EventColor.MAUVE, ORANGE: CalendarApp.EventColor.ORANGE,
      PINK: CalendarApp.EventColor.PINK, TEAL: CalendarApp.EventColor.TEAL,
      TURQUOISE: CalendarApp.EventColor.TURQUOISE, YELLOW: CalendarApp.EventColor.YELLOW,
      RED: CalendarApp.EventColor.RED,
    };
    if (colorMap[type.calColor]) event.setColor(colorMap[type.calColor]);
  } catch(e) { /* non-fatal */ }

  // Send confirmation email (non-fatal if it fails)
  try {
    sendConfirmationEmail(data, type, startDt);
  } catch(emailErr) {
    Logger.log('Email failed: ' + emailErr.message);
  }

  return { success: true, eventId: event.getId() };
}


/** Sends an HTML confirmation email to the client. */
function sendConfirmationEmail(data, type, startDt) {
  var tz   = Session.getScriptTimeZone();
  var date = Utilities.formatDate(startDt, tz, 'EEEE, d MMMM yyyy');
  var time = Utilities.formatDate(startDt, tz, 'h:mm a');

  var addressRow = data.clientAddress
    ? '<tr style="border-bottom:1px solid #f0f0f0"><td style="padding:10px 0;color:#666;font-size:14px">Address</td>'
      + '<td style="padding:10px 0">' + escHtml(data.clientAddress) + '</td></tr>'
    : '';

  var notesSection = data.notes
    ? '<p style="background:#f8f9fa;border-left:3px solid #1a73e8;padding:12px 16px;margin:16px 0;font-size:14px">'
      + '<strong>Notes:</strong> ' + escHtml(data.notes) + '</p>'
    : '';

  var replacements = {
    '{clientName}':      escHtml(data.clientName),
    '{appointmentName}': escHtml(type.name),
    '{date}':            date,
    '{time}':            time,
    '{duration}':        String(type.duration),
    '{ownerName}':       escHtml(CONFIG.ownerName),
    '{address}':         escHtml(data.clientAddress || ''),
    '{notes}':           escHtml(data.notes || ''),
    '{addressRow}':      addressRow,
    '{notesSection}':    notesSection,
  };

  var subject = CONFIG.emailSubject;
  var body    = CONFIG.emailBody;
  Object.keys(replacements).forEach(function(k) {
    subject = subject.split(k).join(replacements[k]);
    body    = body.split(k).join(replacements[k]);
  });

  MailApp.sendEmail({
    to:       data.clientEmail,
    subject:  subject,
    htmlBody: body,
    replyTo:  Session.getActiveUser().getEmail(),
  });
}


// ─── Helpers ──────────────────────────────────────────────────────────────────

function getCalendar() {
  try {
    var cal = CalendarApp.getCalendarById(Session.getActiveUser().getEmail());
    if (cal) return cal;
  } catch(e) {}
  return CalendarApp.getDefaultCalendar();
}

function jsonResponse(data) {
  return ContentService
    .createTextOutput(JSON.stringify(data))
    .setMimeType(ContentService.MimeType.JSON);
}

function escHtml(str) {
  return String(str || '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
