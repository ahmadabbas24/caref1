// CareFirst site script: mobile menu, scroll effects, form validation, confirmation pages
var WA_NUMBER = '923333045046';
var STORE_KEY = 'carefirst_submission';

// ---------- Mobile menu ----------
var menuBtn = document.getElementById('menuBtn');
var links = document.getElementById('links');
if (menuBtn && links) {
  menuBtn.addEventListener('click', function () {
    var open = links.classList.toggle('open');
    menuBtn.setAttribute('aria-expanded', open);
    menuBtn.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
  });
  links.addEventListener('click', function (e) {
    if (e.target.closest('a')) {
      links.classList.remove('open');
      menuBtn.setAttribute('aria-expanded', 'false');
    }
  });
}

// ---------- Scroll reveal ----------
// Everything is visible by default; items below the fold get a small lift that settles as they scroll in.
if ('IntersectionObserver' in window) {
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (en) {
      if (en.isIntersecting) { en.target.classList.remove('pre'); io.unobserve(en.target); }
    });
  }, { rootMargin: '0px 0px -8% 0px' });
  document.querySelectorAll('.reveal').forEach(function (el, i) {
    if (el.getBoundingClientRect().top > window.innerHeight) {
      el.classList.add('pre');
      el.style.transitionDelay = (i % 3) * 70 + 'ms';
      io.observe(el);
    }
  });
}

// ---------- Validation ----------
function today() {
  var d = new Date();
  d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
  return d.toISOString().slice(0, 10);
}

// Returns an error message, or '' when the field is valid.
function checkField(el) {
  var v = (el.value || '').trim();
  var rule = el.dataset.rule;
  if (el.required && !v) {
    return el.tagName === 'SELECT' ? 'Please choose an option.' : 'This field is required.';
  }
  if (!v) return '';
  if (rule === 'name') {
    if (!/^[A-Za-z؀-ۿ][A-Za-z؀-ۿ .'-]*$/.test(v)) return 'Use letters only (no numbers or symbols).';
    if (v.replace(/[^A-Za-z؀-ۿ]/g, '').length < 3) return 'Please enter at least 3 letters.';
  }
  if (rule === 'email' || el.type === 'email') {
    if (!/^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/.test(v)) return 'Enter a valid email, e.g. name@gmail.com.';
  }
  if (rule === 'phone' || el.type === 'tel') {
    var digits = v.replace(/[\s\-()+]/g, '');
    if (!/^\d+$/.test(digits)) return 'Use numbers only, e.g. 0333 3045046.';
    if (!/^(03\d{9}|923\d{9}|00923\d{9})$/.test(digits)) return 'Enter a valid Pakistani mobile number, e.g. 0333 3045046.';
  }
  if (rule === 'age') {
    var n = Number(v);
    if (!Number.isInteger(n) || n < 0 || n > 120) return 'Enter an age between 0 and 120.';
  }
  if (el.type === 'date' && v < today()) return 'Please choose today or a later date.';
  var min = Number(el.dataset.min || 0);
  if (min && v.length < min) return 'Please write at least ' + min + ' characters (' + v.length + '/' + min + ').';
  return '';
}

function showResult(el, msg) {
  var slot = document.getElementById(el.id + '-err');
  if (slot) slot.textContent = msg;
  el.classList.toggle('invalid', !!msg);
  el.classList.toggle('valid', !msg && !!(el.value || '').trim());
  el.setAttribute('aria-invalid', msg ? 'true' : 'false');
  return !msg;
}

function collect(form) {
  var lines = [form.dataset.title, ''];
  var data = {};
  var seen = {};
  Array.prototype.forEach.call(form.elements, function (el) {
    if (!el.name || seen[el.name]) return;
    var val = '';
    if (el.type === 'radio' || el.type === 'checkbox') {
      seen[el.name] = true;
      val = Array.prototype.filter.call(form.querySelectorAll('[name="' + el.name + '"]'), function (x) { return x.checked; })
        .map(function (x) { return x.value; }).join(', ');
    } else {
      val = (el.value || '').trim();
    }
    if (val) { lines.push(el.name + ': ' + val); data[el.name] = val; }
  });
  return { text: lines.join('\n'), data: data };
}

document.querySelectorAll('form.cform').forEach(function (form) {
  var err = form.querySelector('.err');
  var controls = form.querySelectorAll('input:not([type=radio]):not([type=checkbox]), select, textarea');

  form.querySelectorAll('input[type="date"]').forEach(function (d) { d.min = today(); });

  // Check a field when the user leaves it, then live as they fix it
  controls.forEach(function (el) {
    el.addEventListener('blur', function () {
      if ((el.value || '').trim() || el.dataset.touched) { el.dataset.touched = '1'; showResult(el, checkField(el)); }
    });
    el.addEventListener('input', function () {
      if (el.dataset.touched || el.classList.contains('invalid')) showResult(el, checkField(el));
    });
    el.addEventListener('change', function () {
      if (el.tagName === 'SELECT' || el.type === 'date') { el.dataset.touched = '1'; showResult(el, checkField(el)); }
    });
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var firstBad = null;
    controls.forEach(function (el) {
      el.dataset.touched = '1';
      if (!showResult(el, checkField(el)) && !firstBad) firstBad = el;
    });
    err.hidden = !firstBad;
    if (firstBad) {
      form.classList.remove('shake'); void form.offsetWidth; form.classList.add('shake');
      firstBad.focus();
      return;
    }

    var out = collect(form);
    var wa = 'https://wa.me/' + WA_NUMBER + '?text=' + encodeURIComponent(out.text);

    // Forms that open a confirmation page
    if (form.dataset.next) {
      var d = out.data;
      var payload = {
        name: d['Your name'] || d['Full name'] || '',
        email: d['Email'] || '',
        phone: d['Phone / WhatsApp'] || d['Your phone / WhatsApp'] || '',
        patient: d['Patient name'] || '',
        service: d['Service needed'] || '',
        date: d['Preferred start date'] || '',
        city: d['City'] || '',
        wa: wa
      };
      try { sessionStorage.setItem(STORE_KEY, JSON.stringify(payload)); } catch (x) {}
      var q = ['name', 'email', 'phone', 'patient', 'service', 'date', 'city']
        .filter(function (k) { return payload[k]; })
        .map(function (k) { return k + '=' + encodeURIComponent(payload[k]); }).join('&');
      var btn = form.querySelector('button[type=submit]');
      if (btn) { btn.disabled = true; btn.firstChild.textContent = 'Sending… '; }
      window.location.href = form.dataset.next + (q ? '?' + q : '');
      return;
    }

    // Forms that send straight to WhatsApp (job application)
    var done = form.querySelector('.done');
    form.querySelector('.summary').textContent = out.text;
    form.querySelector('.send').href = wa;
    done.hidden = false;
    done.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  });

  var copy = form.querySelector('.copy');
  if (copy) {
    copy.addEventListener('click', function () {
      var summary = form.querySelector('.summary');
      var label = copy.textContent;
      var selectText = function () {
        var r = document.createRange(); r.selectNodeContents(summary);
        var s = window.getSelection(); s.removeAllRanges(); s.addRange(r);
        copy.textContent = 'Selected, press copy';
      };
      if (navigator.clipboard) {
        navigator.clipboard.writeText(summary.textContent).then(function () {
          copy.textContent = 'Copied';
          setTimeout(function () { copy.textContent = label; }, 2000);
        }, selectText);
      } else { selectText(); }
    });
  }
});

// ---------- Confirmation pages: show the submitted name, email, etc. ----------
if (document.querySelector('[data-fill]')) {
  var info = {};
  try { info = JSON.parse(sessionStorage.getItem(STORE_KEY) || '{}') || {}; } catch (x) { info = {}; }
  var params = new URLSearchParams(window.location.search);
  ['name', 'email', 'phone', 'patient', 'service', 'date', 'city'].forEach(function (k) {
    if (!info[k] && params.get(k)) info[k] = params.get(k);
  });
  if (info.date) {
    var dt = new Date(info.date + 'T00:00:00');
    if (!isNaN(dt)) info.date = dt.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
  }
  document.querySelectorAll('[data-fill]').forEach(function (el) { el.textContent = info[el.dataset.fill] || ''; });
  document.querySelectorAll('[data-show]').forEach(function (el) {
    if (!info[el.dataset.show]) el.classList.add('off');
  });
  document.querySelectorAll('[data-wa]').forEach(function (a) { if (info.wa) a.href = info.wa; });
}

// ---------- Placeholder links (e.g. social pages not added yet) ----------
document.querySelectorAll('[data-dummy]').forEach(function (a) {
  a.addEventListener('click', function (e) { e.preventDefault(); });
});

// ---------- Newsletter ----------
document.querySelectorAll('form.news-form').forEach(function (form) {
  var input = form.querySelector('input[type=email]');
  var box = form.querySelector('.news-field');
  var msg = form.querySelector('.news-msg');
  var btn = form.querySelector('button');
  function setMsg(text, ok) {
    msg.textContent = text;
    msg.className = 'news-msg ' + (ok ? 'ok' : 'bad');
    box.classList.toggle('invalid', !ok);
    input.setAttribute('aria-invalid', ok ? 'false' : 'true');
  }
  input.addEventListener('input', function () {
    if (box.classList.contains('invalid')) {
      var bad = checkField(input);
      if (!bad) { msg.textContent = ''; box.classList.remove('invalid'); }
    }
  });
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var v = input.value.trim();
    if (!v) { setMsg('Please enter your email address.', false); input.focus(); return; }
    var bad = checkField(input);
    if (bad) { setMsg(bad, false); input.focus(); return; }
    setMsg('Thank you for subscribing! We’ll send CareFirst updates to ' + v + '.', true);
    input.value = '';
    btn.textContent = 'Subscribed ✓';
    setTimeout(function () { btn.textContent = 'Submit'; }, 4000);
  });
});
