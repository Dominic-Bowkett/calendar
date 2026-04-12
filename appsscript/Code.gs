// ═══════════════════════════════════════════════════════════════════════════════
//  BOOKING APP — Google Apps Script Backend
//  ─────────────────────────────────────────
//  1. Go to script.google.com → New project
//  2. Paste this entire file, replacing any existing code
//  3. Edit the CONFIGURATION section below
//  4. Click Deploy → New deployment → Web app
//     • Execute as: Me
//     • Who has access: Anyone
//  5. Copy the web app URL → paste it into assets/js/config.js
// ═══════════════════════════════════════════════════════════════════════════════


// ─── CONFIGURATION ─────────────────────────────────────────────────────────────
// Edit freely. After changes: Deploy → Manage deployments → New version.
// ───────────────────────────────────────────────────────────────────────────────

var CONFIG = {

  ownerName:    'Your Name',
  ownerEmail:   '',           // leave blank to use your Google account email
  vatRate:      20,           // UK standard VAT rate (%)

  // Slot increment in minutes (15 = 9:00 / 9:15 / 9:30 …)
  slotIncrement: 15,

  // Working hours per day. 0=Sun, 1=Mon … 6=Sat. null = closed.
  workingHours: {
    0: null,
    1: { start: '09:00', end: '17:00' },
    2: { start: '09:00', end: '17:00' },
    3: { start: '09:00', end: '17:00' },
    4: { start: '09:00', end: '17:00' },
    5: { start: '09:00', end: '17:00' },
    6: null,
  },

  // ── Appointment types ────────────────────────────────────────────────────
  // defaultPrice: price including VAT in £
  // calColor:     BLUE | CYAN | GREEN | GREY | MAUVE | ORANGE | PINK | TEAL | TURQUOISE | YELLOW | RED
  // ─────────────────────────────────────────────────────────────────────────
  appointmentTypes: [
    {
      id:           'epc',
      name:         'EPC Assessment',
      duration:     60,
      defaultPrice: 75.00,   // fallback if bedroom count not selected
      // Price (inc VAT) by number of bedrooms — edit freely.
      // Keys must match the bedroom options in the booking form.
      // Remove this property from a type to use a fixed defaultPrice only.
      priceByBedrooms: {
        'Studio': 65.00,
        '1':      70.00,
        '2':      75.00,
        '3':      85.00,
        '4':      95.00,
        '5':     110.00,
        '6+':    130.00,
      },
      color:        '#1a73e8',
      calColor:     'BLUE',
      description:  'Energy Performance Certificate assessment',
    },
    {
      id:           'floorplan',
      name:         'Floor Plan',
      duration:     90,
      defaultPrice: 85.00,
      color:        '#188038',
      calColor:     'GREEN',
      description:  'Property floor plan measurement and drawing',
    },
    {
      id:           'epc_floorplan',
      name:         'EPC + Floor Plan',
      duration:     120,
      defaultPrice: 140.00,
      color:        '#6200ea',
      calColor:     'MAUVE',
      description:  'Combined EPC assessment and floor plan',
    },
    {
      id:           'consultation',
      name:         'Consultation',
      duration:     30,
      defaultPrice: 0.00,
      color:        '#e37400',
      calColor:     'YELLOW',
      description:  'General consultation',
    },
  ],

  // ── Confirmation email ───────────────────────────────────────────────────
  // Placeholders: {clientName} {appointmentName} {date} {time} {duration}
  //               {ownerName} {address} {bedrooms} {price} {ref} {notes}
  emailSubject: 'Booking Confirmed — {appointmentName} on {date}',

  emailBody: `
    <div style="font-family:Arial,Helvetica,sans-serif;max-width:580px;margin:0 auto;color:#222">
      <div style="background:#1a73e8;padding:24px 28px;border-radius:8px 8px 0 0">
        <h1 style="color:#fff;margin:0;font-size:22px">Booking Confirmed</h1>
        <p style="color:rgba(255,255,255,.8);margin:6px 0 0;font-size:14px">Ref: {ref}</p>
      </div>
      <div style="background:#fff;padding:28px;border:1px solid #e0e0e0;border-top:none;border-radius:0 0 8px 8px">
        <p style="margin-top:0">Hi {clientName},</p>
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
          <tr style="border-bottom:1px solid #f0f0f0">
            <td style="padding:10px 0;color:#666;font-size:14px">Address</td>
            <td style="padding:10px 0">{address}</td>
          </tr>
          <tr style="border-bottom:1px solid #f0f0f0">
            <td style="padding:10px 0;color:#666;font-size:14px">Bedrooms</td>
            <td style="padding:10px 0">{bedrooms}</td>
          </tr>
          <tr style="border-bottom:1px solid #f0f0f0">
            <td style="padding:10px 0;color:#666;font-size:14px">Price (inc VAT)</td>
            <td style="padding:10px 0;font-weight:600">£{price}</td>
          </tr>
          {notesRow}
        </table>

        <p style="margin-top:24px;color:#555;font-size:14px">
          If you need to reschedule or cancel, please get in touch as soon as possible.
        </p>
        <p style="color:#555;font-size:14px">
          Kind regards,<br><strong>{ownerName}</strong>
        </p>
      </div>
    </div>
  `,

  // ── 24-hour email reminder ───────────────────────────────────────────────
  // To activate: after deploying, go to Apps Script → Triggers (clock icon) →
  // Add trigger → sendReminders → Time-driven → Day timer → e.g. 8am–9am
  // This will run once a day and email clients whose appointment is tomorrow.
  reminderSubject: 'Reminder — {appointmentName} Tomorrow at {time}',
  reminderBody: `
    <div style="font-family:Arial,Helvetica,sans-serif;max-width:580px;margin:0 auto;color:#222">
      <div style="background:#1a73e8;padding:24px 28px;border-radius:8px 8px 0 0">
        <h1 style="color:#fff;margin:0;font-size:22px">Appointment Reminder</h1>
      </div>
      <div style="background:#fff;padding:28px;border:1px solid #e0e0e0;border-top:none;border-radius:0 0 8px 8px">
        <p style="margin-top:0">Hi {clientName},</p>
        <p>This is a friendly reminder that you have an appointment <strong>tomorrow</strong>:</p>
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
            <td style="padding:10px 0;color:#666;font-size:14px">Address</td>
            <td style="padding:10px 0">{address}</td>
          </tr>
        </table>
        <p style="color:#555;font-size:14px">See you tomorrow!<br><strong>{ownerName}</strong></p>
      </div>
    </div>
  `,

};

// ═══════════════════════════════════════════════════════════════════════════════
//  CORE CODE — no need to edit below this line
// ═══════════════════════════════════════════════════════════════════════════════

function doGet(e) {
  var result;
  try {
    var action = (e.parameter && e.parameter.action) || '';
    if      (action === 'config') result = getPublicConfig();
    else if (action === 'slots')  result = getAvailableSlots(e.parameter.typeId, e.parameter.date);
    else                          result = { error: 'Unknown action' };
  } catch (err) {
    result = { error: err.message };
  }
  return jsonResponse(result);
}

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
      bedrooms:      p.bedrooms      || '',
      priceIncVAT:   p.priceIncVAT   || '0',
      notes:         p.notes         || '',
    });
  } catch (err) {
    result = { success: false, error: err.message };
  }
  return jsonResponse(result);
}

// ── Public config (safe for frontend) ────────────────────────────────────────

function getPublicConfig() {
  return {
    ownerName:     CONFIG.ownerName,
    slotIncrement: CONFIG.slotIncrement,
    vatRate:       CONFIG.vatRate,
    workingHours:  CONFIG.workingHours,
    appointmentTypes: CONFIG.appointmentTypes.map(function(t) {
      return { id: t.id, name: t.name, duration: t.duration,
               defaultPrice: t.defaultPrice,
               priceByBedrooms: t.priceByBedrooms || null,
               color: t.color, description: t.description };
    }),
  };
}

// ── Available slots ───────────────────────────────────────────────────────────

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

  var tz       = Session.getScriptTimeZone();
  var sParts   = hours.start.split(':');
  var eParts   = hours.end.split(':');
  var dayStart = new Date(year, month, day, parseInt(sParts[0]), parseInt(sParts[1]), 0, 0);
  var dayEnd   = new Date(year, month, day, parseInt(eParts[0]), parseInt(eParts[1]), 0, 0);
  var now      = new Date();
  var stepMs   = CONFIG.slotIncrement * 60000;
  var durMs    = type.duration        * 60000;

  var slots = [];
  var cursor = new Date(dayStart.getTime());
  while (true) {
    var slotEnd = new Date(cursor.getTime() + durMs);
    if (slotEnd > dayEnd) break;
    if (cursor > now) slots.push({ start: cursor.getTime(), end: slotEnd.getTime() });
    cursor = new Date(cursor.getTime() + stepMs);
  }
  if (slots.length === 0) return [];

  var calendar = getCalendar();
  var events   = calendar.getEvents(new Date(year, month, day, 0, 0, 0), new Date(year, month, day, 23, 59, 59));
  var busy     = events.map(function(ev) {
    return { start: ev.getStartTime().getTime(), end: ev.getEndTime().getTime() };
  });

  return slots
    .filter(function(s) {
      return !busy.some(function(b) { return s.start < b.end && s.end > b.start; });
    })
    .map(function(s) {
      var d = new Date(s.start);
      return { time: Utilities.formatDate(d, tz, 'HH:mm'), label: Utilities.formatDate(d, tz, 'h:mm a') };
    });
}

// ── Create booking ────────────────────────────────────────────────────────────

function createBooking(data) {
  var type = CONFIG.appointmentTypes.filter(function(t) { return t.id === data.typeId; })[0];
  if (!type)             throw new Error('Unknown appointment type');
  if (!data.clientName)  throw new Error('Client name is required');
  if (!data.clientEmail) throw new Error('Client email is required');
  if (!data.date || !data.time) throw new Error('Date and time are required');

  var parts   = data.date.split('-');
  var tParts  = data.time.split(':');
  var startDt = new Date(parseInt(parts[0]), parseInt(parts[1])-1, parseInt(parts[2]),
                         parseInt(tParts[0]), parseInt(tParts[1]), 0, 0);
  var endDt   = new Date(startDt.getTime() + type.duration * 60000);

  var calendar  = getCalendar();
  var conflicts = calendar.getEvents(startDt, endDt);
  if (conflicts.length > 0) {
    return { success: false, error: 'This slot is no longer available. Please choose another time.' };
  }

  // ── Financial calculations (ready for Xero) ──────────────────────────────
  var priceIncVAT = parseFloat(data.priceIncVAT) || 0;
  var vatRate     = CONFIG.vatRate / 100;
  var priceExVAT  = Math.round((priceIncVAT / (1 + vatRate)) * 100) / 100;
  var vatAmount   = Math.round((priceIncVAT - priceExVAT) * 100) / 100;
  var ref         = 'BK-' + Utilities.formatDate(startDt, Session.getScriptTimeZone(), 'yyyyMMdd-HHmm');

  // ── Calendar event description (structured for future Xero parsing) ──────
  var desc = [
    '── CLIENT ──────────────────────────',
    'Name:     ' + data.clientName,
    'Email:    ' + data.clientEmail,
    data.clientPhone   ? 'Phone:    ' + data.clientPhone   : null,
    data.clientAddress ? 'Address:  ' + data.clientAddress : null,
    data.bedrooms      ? 'Bedrooms: ' + data.bedrooms      : null,
    '',
    '── INVOICE ─────────────────────────',
    'Ref:          ' + ref,
    'Description:  ' + type.name,
    'Price (inc VAT): £' + priceIncVAT.toFixed(2),
    'Price (ex VAT):  £' + priceExVAT.toFixed(2),
    'VAT (' + CONFIG.vatRate + '%):      £' + vatAmount.toFixed(2),
    '',
    data.notes ? '── NOTES ──────────────────────────\n' + data.notes : null,
  ].filter(function(l) { return l !== null; }).join('\n');

  // Create the Calendar event
  var event = calendar.createEvent(
    type.name + ' \u2014 ' + data.clientName,
    startDt, endDt,
    { description: desc }
  );

  // Store structured metadata as extended properties (for future Xero API use)
  try {
    event.setTag('bookingRef',    ref);
    event.setTag('clientEmail',   data.clientEmail);
    event.setTag('clientPhone',   data.clientPhone);
    event.setTag('clientAddress', data.clientAddress);
    event.setTag('bedrooms',      data.bedrooms);
    event.setTag('priceIncVAT',   priceIncVAT.toFixed(2));
    event.setTag('priceExVAT',    priceExVAT.toFixed(2));
    event.setTag('vatAmount',     vatAmount.toFixed(2));
    event.setTag('vatRate',       String(CONFIG.vatRate));
    event.setTag('typeId',        data.typeId);
    event.setTag('typeName',      type.name);
  } catch(e) { /* non-fatal */ }

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

  // Send confirmation email
  try {
    sendConfirmationEmail(data, type, startDt, ref, priceIncVAT);
  } catch(emailErr) {
    Logger.log('Confirmation email failed: ' + emailErr.message);
  }

  return { success: true, eventId: event.getId(), ref: ref };
}

// ── Confirmation email ────────────────────────────────────────────────────────

function sendConfirmationEmail(data, type, startDt, ref, priceIncVAT) {
  var tz   = Session.getScriptTimeZone();
  var date = Utilities.formatDate(startDt, tz, 'EEEE, d MMMM yyyy');
  var time = Utilities.formatDate(startDt, tz, 'h:mm a');

  var notesRow = data.notes
    ? '<tr style="border-bottom:1px solid #f0f0f0">'
      + '<td style="padding:10px 0;color:#666;font-size:14px">Notes</td>'
      + '<td style="padding:10px 0">' + escHtml(data.notes) + '</td></tr>'
    : '';

  var replacements = {
    '{clientName}':      escHtml(data.clientName),
    '{appointmentName}': escHtml(type.name),
    '{date}':            date,
    '{time}':            time,
    '{duration}':        String(type.duration),
    '{ownerName}':       escHtml(CONFIG.ownerName),
    '{address}':         escHtml(data.clientAddress || '—'),
    '{bedrooms}':        escHtml(data.bedrooms || '—'),
    '{price}':           priceIncVAT.toFixed(2),
    '{ref}':             ref,
    '{notes}':           escHtml(data.notes || ''),
    '{notesRow}':        notesRow,
  };

  var subject = CONFIG.emailSubject;
  var body    = CONFIG.emailBody;
  Object.keys(replacements).forEach(function(k) {
    subject = subject.split(k).join(replacements[k]);
    body    = body.split(k).join(replacements[k]);
  });

  var fromEmail = CONFIG.ownerEmail || Session.getActiveUser().getEmail();

  // Build an .ics calendar invite the customer can add to their own calendar.
  // Most mail clients (Gmail, Outlook, Apple Mail) recognise this and show
  // an inline "Add to calendar" / RSVP card.
  var endDt   = new Date(startDt.getTime() + type.duration * 60000);
  var ics     = buildIcsInvite(data, type, startDt, endDt, ref, fromEmail);
  var icsBlob = Utilities.newBlob(ics, 'text/calendar; method=REQUEST; charset=UTF-8', 'invite.ics');

  MailApp.sendEmail({
    to:          data.clientEmail,
    subject:     subject,
    htmlBody:    body,
    replyTo:     fromEmail,
    attachments: [icsBlob],
  });
}

// ── ICS calendar invite builder (RFC 5545) ───────────────────────────────────
// Produces a VCALENDAR/VEVENT string the customer can add to their own
// calendar straight from the confirmation email.

function buildIcsInvite(data, type, startDt, endDt, ref, organizerEmail) {
  function fmtUtc(d) {
    return Utilities.formatDate(d, 'UTC', "yyyyMMdd'T'HHmmss'Z'");
  }
  // Escape text values per RFC 5545 section 3.3.11
  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/\\/g, '\\\\')
      .replace(/;/g,  '\\;')
      .replace(/,/g,  '\\,')
      .replace(/\r\n|\r|\n/g, '\\n');
  }
  // Quote a parameter value (CN=, etc.) and strip embedded double-quotes
  function paramEsc(s) {
    return '"' + String(s == null ? '' : s).replace(/"/g, "'") + '"';
  }
  // Fold any line longer than 73 octets per RFC 5545 section 3.1
  function fold(line) {
    if (line.length <= 73) return line;
    var out = '';
    while (line.length > 73) {
      out += line.substring(0, 73) + '\r\n ';
      line = line.substring(73);
    }
    return out + line;
  }

  var ownerName = CONFIG.ownerName || 'Booking';
  var domain    = (organizerEmail && organizerEmail.split('@')[1]) || 'booking.local';
  var uid       = ref + '@' + domain;

  // Multi-line description shown when the customer opens the event in
  // their calendar. \n is escaped by esc() into the literal sequence
  // \\n which calendar clients render as a line break.
  var descLines = [type.name, 'Reference: ' + ref];
  if (data.clientAddress) descLines.push('Address: ' + data.clientAddress);
  if (data.clientPhone)   descLines.push('Phone: '   + data.clientPhone);
  if (data.notes)         descLines.push('', 'Notes: ' + data.notes);
  descLines.push('', 'If you need to reschedule, please contact ' + ownerName + '.');
  var description = descLines.join('\n');

  var lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//' + ownerName + '//Booking App//EN',
    'CALSCALE:GREGORIAN',
    'METHOD:REQUEST',
    'BEGIN:VEVENT',
    'UID:' + uid,
    'DTSTAMP:'  + fmtUtc(new Date()),
    'DTSTART:'  + fmtUtc(startDt),
    'DTEND:'    + fmtUtc(endDt),
    'SUMMARY:'  + esc(type.name),
    'DESCRIPTION:' + esc(description),
    'LOCATION:' + esc(data.clientAddress || ''),
    'ORGANIZER;CN=' + paramEsc(ownerName) + ':mailto:' + organizerEmail,
    'ATTENDEE;CN=' + paramEsc(data.clientName)
      + ';ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE'
      + ':mailto:' + data.clientEmail,
    'STATUS:CONFIRMED',
    'SEQUENCE:0',
    'TRANSP:OPAQUE',
    'END:VEVENT',
    'END:VCALENDAR',
  ];

  return lines.map(fold).join('\r\n');
}

// ── One-time setup: programmatically create the daily reminder trigger ────────
// Run this function ONCE from the Apps Script editor after deploying:
//   Run → Run function → createDailyReminderTrigger
// It sets up a daily 8 am trigger so sendReminders() fires automatically.
// Safe to re-run — it removes any existing sendReminders trigger first.

function createDailyReminderTrigger() {
  // Remove existing triggers to avoid duplicates
  ScriptApp.getProjectTriggers().forEach(function (trigger) {
    if (trigger.getHandlerFunction() === 'sendReminders') {
      ScriptApp.deleteTrigger(trigger);
    }
  });
  // Create a new daily trigger at 8 am (script timezone)
  ScriptApp.newTrigger('sendReminders')
    .timeBased()
    .everyDays(1)
    .atHour(8)
    .create();
  Logger.log('✓ Daily reminder trigger created — sendReminders will run at 8 am each day.');
}

// ── 24-hour reminder ──────────────────────────────────────────────────────────
// Activated by createDailyReminderTrigger() above.
// Finds all Google Calendar events tomorrow that have a clientEmail tag
// (i.e. were created by this booking app) and emails each client a reminder.

function sendReminders() {
  var tz       = Session.getScriptTimeZone();
  var calendar = getCalendar();
  var tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);

  var tomorrowStart = new Date(tomorrow.getFullYear(), tomorrow.getMonth(), tomorrow.getDate(), 0, 0, 0);
  var tomorrowEnd   = new Date(tomorrow.getFullYear(), tomorrow.getMonth(), tomorrow.getDate(), 23, 59, 59);
  var events        = calendar.getEvents(tomorrowStart, tomorrowEnd);

  events.forEach(function(event) {
    var clientEmail = event.getTag('clientEmail');
    if (!clientEmail) return; // not a booking app event

    var clientName      = (event.getTitle().split(' \u2014 ')[1] || '').trim();
    var typeName        = (event.getTitle().split(' \u2014 ')[0] || event.getTitle()).trim();
    var clientAddress   = event.getTag('clientAddress') || '';
    var date            = Utilities.formatDate(event.getStartTime(), tz, 'EEEE, d MMMM yyyy');
    var time            = Utilities.formatDate(event.getStartTime(), tz, 'h:mm a');

    var replacements = {
      '{clientName}':      escHtml(clientName),
      '{appointmentName}': escHtml(typeName),
      '{date}':            date,
      '{time}':            time,
      '{ownerName}':       escHtml(CONFIG.ownerName),
      '{address}':         escHtml(clientAddress || '—'),
    };

    var subject = CONFIG.reminderSubject;
    var body    = CONFIG.reminderBody;
    Object.keys(replacements).forEach(function(k) {
      subject = subject.split(k).join(replacements[k]);
      body    = body.split(k).join(replacements[k]);
    });

    try {
      MailApp.sendEmail({ to: clientEmail, subject: subject, htmlBody: body });
      Logger.log('Reminder sent to ' + clientEmail + ' for ' + date + ' at ' + time);
    } catch(e) {
      Logger.log('Reminder failed for ' + clientEmail + ': ' + e.message);
    }
  });
}

// ── SMS hook (plug in Twilio / TextLocal / Vonage when ready) ─────────────────
// To add SMS: replace the stub below with an HTTP call to your SMS provider.
// The phone number is stored in the Calendar event tag 'clientPhone'.
//
// Example Twilio (add your credentials to Script Properties first):
//
// function sendSms(to, message) {
//   var props   = PropertiesService.getScriptProperties();
//   var sid     = props.getProperty('TWILIO_SID');
//   var token   = props.getProperty('TWILIO_TOKEN');
//   var from    = props.getProperty('TWILIO_FROM');
//   var url     = 'https://api.twilio.com/2010-04-01/Accounts/' + sid + '/Messages.json';
//   UrlFetchApp.fetch(url, {
//     method: 'post',
//     headers: { Authorization: 'Basic ' + Utilities.base64Encode(sid + ':' + token) },
//     payload: { To: to, From: from, Body: message },
//   });
// }


// ─── Helpers ──────────────────────────────────────────────────────────────────

function getCalendar() {
  try {
    var cal = CalendarApp.getCalendarById(Session.getActiveUser().getEmail());
    if (cal) return cal;
  } catch(e) {}
  return CalendarApp.getDefaultCalendar();
}

function jsonResponse(data) {
  return ContentService.createTextOutput(JSON.stringify(data)).setMimeType(ContentService.MimeType.JSON);
}

function escHtml(str) {
  return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
