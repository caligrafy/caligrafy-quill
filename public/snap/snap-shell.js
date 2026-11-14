/*
 * The Snap shell (prototype, redesigned after the 2026-11-14 reviews): Home and the task space, one page, two modes.
 * Home shows minimal cards (size="tile"); any of them opens in the task space (snap_ask open). In the task space the
 * cards are "voiced" (the assistant's line says what they are about) and the suggestions say what may come next;
 * each suggestion runs its card at once (snap_ask run).
 * A constant conversation: every action (typing, a suggestion, anything done on a card) gets a line from the
 * assistant in the conversation section, typed out; done things are celebrated (confetti, a toast, your counter);
 * what needs you calls for attention (a pulse), once, without nagging.
 * The address follows: /assistant (Home), /assistant?c=<conversation>&t=<turn> (an answer).
 * While it works it says what it is doing ("Adding Kindred to the shelf…", "Looking through the Bookshelf…"), its mark
 * thinks and a ghost card holds the place. Alt+K types, Alt+M talks (/ starts typing too); the + attaches files; once
 * the page scrolls, a compact conversation stays pinned under the top bar.
 */
(function () {
  'use strict'
  var meta = function (name) { var m = document.querySelector('meta[name="' + name + '"]'); return m ? m.content : '' }
  var HOME = meta('snap-home') || '/'
  var ASSISTANT = meta('snap-assistant') || 'assistant'   // Route::snap(): where the assistant is ('assistant' by default)
  var REDUCED = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches
  var app = document.getElementById('snap-app')
  var cardsBox = document.getElementById('snap-cards')
  var message = document.getElementById('snap-message')
  var suggestionsBox = document.getElementById('snap-suggestions')
  var form = document.getElementById('snap-ask')
  var input = document.getElementById('snap-text')
  var openInput = document.getElementById('snap-open-input')
  var historyPanel = document.getElementById('snap-history')
  var historyBtn = document.getElementById('snap-history-btn')
  var progressBox = document.getElementById('snap-progress')
  var MORE = 'What else can I do?'
  var APP = (document.title.split('·')[1] || 'app').trim()
  var MAC = /Mac|iPhone|iPad/.test(navigator.platform || '')
  var KEYS = { type: MAC ? '⌥K' : 'Alt+K', talk: MAC ? '⌥M' : 'Alt+M' }
  var compactLine = document.getElementById('snap-compact-line')

  var home = JSON.parse(document.getElementById('snap-home-data').textContent)
  var state = { conversation: null, turns: [], index: -1 }

  // ---- the conversation's memory: what it is about now and what the person chose, sent with every ask ----
  // focus: the record the conversation is about (the book opened, the one being lent); selections: what the person
  // did, in their words ("opened Dory", "picked Kindred", "confirmed Lend a book"). The cards on screen are read when
  // asking. All of it is cleared when a new conversation starts from Home.
  var memory = { focus: null, selections: [] }
  function remember (what) { if (what) { memory.selections.push(what); memory.selections = memory.selections.slice(-8) } }
  function focusOn (c) {
    if (!c || !c.capability) return
    var input = c.input || {}
    var name = c.heading || c.subject || ''
    if (c.type === 'answer' && c.variant === 'record' && name) memory.focus = { capability: c.capability, title: String(name).split(' · ')[0], input: input }
    else if (c.type === 'confirm' && c.subject) memory.focus = { capability: c.capability, title: String(c.subject).split(' · ')[0], input: c.inputs || input }
  }
  function onScreen () {
    return Array.prototype.slice.call(cardsBox.querySelectorAll(':scope > .snap-row > snap-card')).slice(0, 4).map(function (n) {
      var c = n.card || {}
      return { capability: c.capability || '', type: c.type || '', title: c.title || '', about: c.heading || c.subject || c.glance || '', input: c.input || {} }
    })
  }
  function context () { return { focus: memory.focus, screen: onScreen(), selections: memory.selections } }

  function el (tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text !== undefined && text !== null) n.textContent = String(text); return n }
  function icon (name, size) { return window.Snap.icon(name, size) }
  function card (c, attention, size) {
    var n = document.createElement('snap-card')
    if (size) n.setAttribute('size', size); else n.setAttribute('voiced', '')
    if (attention) c = Object.assign({}, c, { attention: true })
    n.card = c
    return n
  }
  function post (path, body) {
    return fetch(HOME + path, { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': meta('snap-csrf') }, body: JSON.stringify(body) })
      .then(function (r) { return r.json() })
  }

  // icons of the page's own buttons: data-icon="history"
  function applyIcons (root) {
    root.querySelectorAll('[data-icon]').forEach(function (n) {
      if (n.querySelector('.snap-i')) return
      n.insertBefore(icon(n.dataset.icon, n.classList.contains('snap-mark') ? 28 : 18), n.firstChild)
    })
  }

  // ---- the assistant's voice: a line, typed out ----
  var typing = null
  var said = ''   // the assistant's line now (the one to come back to)
  // lines (Home: one idea a line, two at most), each its own element once typed; focus: the first says what needs you
  function asLines (lines, focus) {
    message.textContent = ''
    lines.forEach(function (l, i) { message.appendChild(el('span', 'snap-line' + (i === 0 && focus ? ' is-focus' : ''), l)) })
  }
  function speakLine (text, spoken, focus) {
    var lines = Array.isArray(text) ? text.filter(Boolean) : null
    if (lines) text = lines.join('\n')
    if (!text) return
    stopThinking()
    said = text
    if (compactLine) compactLine.textContent = text.replace(/\n/g, '  ·  ')
    clearInterval(typing)
    if (REDUCED) { if (lines) asLines(lines, focus); else message.textContent = text; return }
    message.textContent = ''
    message.classList.add('is-typing')
    var i = 0
    typing = setInterval(function () {
      i += Math.max(1, Math.round(text.length / 45))
      message.textContent = text.slice(0, i)
      if (i >= text.length) { clearInterval(typing); message.classList.remove('is-typing'); if (lines) asLines(lines, focus) }
    }, 18)
    if (spoken && 'speechSynthesis' in window) { window.speechSynthesis.cancel(); window.speechSynthesis.speak(new SpeechSynthesisUtterance(text)) }
  }

  // ---- while it works: what it is doing, said; the mark thinks ----
  var thinking = null
  function think (phrases) {
    clearInterval(typing); clearInterval(thinking)
    phrases = [].concat(phrases)
    app.classList.add('is-thinking')
    message.classList.remove('is-typing')
    var i = 0
    var next = function () {
      var line = phrases[Math.min(i, phrases.length - 1)]
      message.textContent = line
      var dots = el('span', 'snap-dots'); dots.setAttribute('aria-hidden', 'true')
      dots.appendChild(el('i')); dots.appendChild(el('i')); dots.appendChild(el('i'))
      message.appendChild(dots)
      if (compactLine) compactLine.textContent = line
      message.classList.remove('is-swap'); void message.offsetWidth; message.classList.add('is-swap')
      i++
      if (i >= phrases.length) clearInterval(thinking)
    }
    next()
    if (phrases.length > 1) thinking = setInterval(next, 1300)
  }
  function stopThinking () { clearInterval(thinking); app.classList.remove('is-thinking'); message.classList.remove('is-swap') }

  // what it is doing, from what was asked: "Adding Kindred to the shelf…", "Opening the catalog…"
  function doing (capability, subject, title, prepare) {
    var verb = String(capability || '').split('_')[0]
    var what = subject ? '"' + subject + '"' : ''
    // before a change is confirmed nothing changes: it is only being prepared
    if (prepare && /^(create|update|delete|lend|return|remind|send|register|renew)$/.test(verb)) return ['Getting ' + (what || 'it') + ' ready…']
    var lower = function (t) { return t ? (/^[A-Z][a-z]/.test(t) ? t[0].toLowerCase() + t.slice(1) : t) : '' }
    switch (verb) {
      case 'create': return ['Adding ' + (what || 'it') + ' to the ' + APP + '…', 'Almost there…']
      case 'update': return ['Saving your changes' + (what ? ' to ' + what : '') + '…']
      case 'delete': return ['Deleting ' + (what || 'it') + '…']
      case 'lend': return ['Lending ' + (what || 'the book') + '…', 'Writing down the due date…']
      case 'return': return ['Recording the return' + (what ? ' of ' + what : '') + '…']
      case 'remind': case 'send': return ['Sending the reminders…']
      case 'search': return ['Searching the ' + APP + '…']
      case 'list': return ['Gathering ' + (lower(title) || 'the list') + '…']
      case 'get': return ['Opening ' + (what || 'it') + '…']
      default: return ['Working on it…']
    }
  }
  // a card is working (a send, a confirm, an undo, a row opened)
  document.addEventListener('snap:busy', function (e) {
    var d = e.detail || {}
    var c = d.card || {}
    // what the person chose, remembered in their words
    var verb = String(d.capability || '').split('_')[0]
    var thing = d.label || (c.subject ? String(c.subject).split(' · ')[0] : '') || (d.inputs && (d.inputs.title || d.inputs.name)) || ''
    remember(d.confirm ? 'confirmed ' + (c.title || d.capability) + (thing ? ': ' + thing : '')
      : ({ get: 'opened ', list: 'opened ', search: 'searched ' }[verb] || (c.type === 'choice' ? 'picked ' : 'started ')) + (thing || (c.title || d.capability)))
    var subject = d.label || (c.subject ? String(c.subject).split(' · ')[0] : '') || (d.inputs && (d.inputs.title || d.inputs.name)) || ''
    think(doing(d.capability, subject, c.title, !d.confirm))
  })

  // every action on a card ends here: the assistant says its line (the latest answer of the task space follows it)
  document.addEventListener('snap:say', function (e) {
    speakLine(e.detail.text)
    var t = state.turns[state.index]
    if (t && app.dataset.mode === 'task') t.lastLine = e.detail.text
  })
  // the card moved on: what may come next follows it
  document.addEventListener('snap:next', function (e) { if (e.detail) focusOn(e.detail) })
  document.addEventListener('snap:next', function (e) {
    if (app.dataset.mode !== 'task' || !e.detail || !e.detail.next) return
    var t = state.turns[state.index]
    if (t) t.suggestions = e.detail.next
    suggestions(e.detail.next)
  })
  // a Home card's one action (Send reminders, Yes): it runs in the task space
  document.addEventListener('snap:go', function (e) { var d = e.detail || {}; if (d.run) send({ text: d.text, run: d.run }, doing(d.run.capability, '', d.text, true)) })
  // a card left Home (Not now): Home is drawn again, its rows full
  document.addEventListener('snap:gone', function (e) {
    var grid = e.target.closest && e.target.closest('.snap-grid')
    setTimeout(function () {
      if (!grid) return
      var left = grid.querySelectorAll('snap-card').length
      if (!left) { var sec = grid.closest('.snap-home-section'); if (sec) sec.remove(); return }
      if (!grid.classList.contains('snap-grid--banner')) grid.style.setProperty('--cols', left)   // the row stays full
    }, 0)
  })
  // a Home card: it opens in the task space
  document.addEventListener('snap:open', function (e) { if (e.detail && e.detail.key) send({ open: e.detail.key }, ['Opening "' + e.detail.title + '"…']) })

  // ---- celebrations ----
  function celebrate (node) {
    bump()
    if (REDUCED || typeof window.confetti !== 'function') return
    var r = node && node.getBoundingClientRect ? node.getBoundingClientRect() : { left: window.innerWidth / 2, top: window.innerHeight / 2, width: 0, height: 0 }
    var origin = { x: (r.left + r.width / 2) / window.innerWidth, y: Math.min(0.9, (r.top + 60) / window.innerHeight) }
    window.confetti({ particleCount: 90, spread: 70, startVelocity: 38, origin: origin, colors: ['#5b3fd3', '#7c5cff', '#c0377a', '#f59e0b', '#1f9d6a'], scalar: 0.9, disableForReducedMotion: true })
    setTimeout(function () { window.confetti({ particleCount: 40, spread: 100, startVelocity: 24, origin: origin, colors: ['#ffd166', '#7c5cff'], scalar: 0.7, disableForReducedMotion: true }) }, 180)
  }
  document.addEventListener('snap:celebrate', function (e) { celebrate(e.target) })

  function toast (text, iconName) {
    var t = el('div', 'snap-toast')
    t.setAttribute('role', 'status')
    t.appendChild(icon(iconName || 'party-popper', 18))
    t.appendChild(el('span', null, text))
    document.body.appendChild(t)
    setTimeout(function () { t.remove() }, 2700)
  }

  // your progress: the streak and what you got done today; a celebration adds one, with a pop and a milestone toast
  function progress () {
    progressBox.textContent = ''
    var p = home.progress || {}
    if (p.streak > 1) {
      var s = el('span', 'snap-pchip snap-pchip--streak')
      s.appendChild(icon('flame', 15)); s.appendChild(el('span', null, p.streak + '-day streak'))
      progressBox.appendChild(s)
    }
    var d = el('span', 'snap-pchip snap-pchip--today')
    d.id = 'snap-today'
    d.appendChild(icon('circle-check', 15)); d.appendChild(el('span', null, (p.today || 0) + ' done today'))
    progressBox.appendChild(d)
  }
  function bump () {
    home.progress = home.progress || { today: 0, streak: 0 }
    home.progress.today = (home.progress.today || 0) + 1
    if (home.progress.streak < 1) home.progress.streak = 1
    progress()
    var d = document.getElementById('snap-today')
    if (d) d.classList.add('is-bumped')
    if ([1, 5, 10, 25].indexOf(home.progress.today) >= 0) toast(home.progress.today === 1 ? 'First thing done today!' : home.progress.today + ' things done today. Nice!', 'trophy')
  }

  // ---- suggestions ----
  function suggestions (list) {
    suggestionsBox.textContent = ''
    // no real suggestion (at most "What else can I do?"): the area is not shown at all
    var real = (list || []).filter(function (s) { return (typeof s === 'string' ? s : s.text) !== MORE })
    suggestionsBox.hidden = !real.length
    if (!real.length) return
    ;(list || []).forEach(function (s, i) {
      var text = typeof s === 'string' ? s : s.text
      var chip = el('button', 'snap-suggestion' + (text === MORE ? ' snap-suggestion--more' : ''))
      chip.type = 'button'
      chip.style.setProperty('--i', i)
      chip.appendChild(icon((s && s.icon) || (text === MORE ? 'sparkles' : 'message-circle'), 16))
      chip.appendChild(el('span', null, text))
      chip.addEventListener('click', function () { suggest(s, text) })
      suggestionsBox.appendChild(chip)
    })
  }

  // a suggestion: a detail of the form on screen opens (the assistant says so), a card runs at once, words are asked
  function suggest (s, text) {
    if (s && s.reveal) {
      var forms = cardsBox.querySelectorAll('snap-card')
      for (var i = 0; i < forms.length; i++) {
        if (forms[i].card && forms[i].card.type === 'form' && forms[i].reveal(s.reveal)) { speakLine('Sure, add the ' + text.replace(/^Add /, '') + ' right here.'); return }
      }
    }
    if (s && s.home) return goHome()
    if (s && s.open) return send({ open: s.open }, ['Opening ' + text + '…'])
    if (s && s.run) return send({ text: text, run: s.run }, doing(s.run.capability, /^Look at /.test(text) ? text.replace(/^Look at /, '') : '', text))
    ask(text)
  }

  function ask (text, again, spoken) {
    if (!again && !String(text || '').trim()) return
    send(again ? { again: again } : { text: text }, again ? ['Thinking of another way…', 'Looking through the ' + APP + '…', 'Picking a better card…']
      : ['Reading what you asked…', 'Looking through the ' + APP + '…', 'Picking the right card…'], spoken)
  }

  // a ghost card holds the place while the answer comes
  function ghost () {
    var g = el('div', 'snap-ghost')
    g.setAttribute('aria-hidden', 'true')
    ;['70%', '45%', '85%', '60%'].forEach(function (w) { var l = el('span', 'snap-ghost-line'); l.style.width = w; g.appendChild(l) })
    var row = el('div', 'snap-row snap-row--task')
    row.appendChild(g)
    return row
  }

  // every request of the shell: a turn of the current conversation (a new one from Home)
  function send (body, waiting, spoken) {
    var fromHome = app.dataset.mode === 'home'
    var ctx = !fromHome && state.conversation ? context() : null   // read before the answer replaces the screen
    setMode('task')
    if (fromHome) window.scrollTo({ top: 0, behavior: REDUCED ? 'auto' : 'smooth' })   // the answer starts at the top
    think(waiting)
    cardsBox.setAttribute('aria-busy', 'true')
    cardsBox.textContent = ''
    cardsBox.appendChild(ghost())
    if (fromHome) { state.conversation = null; state.turns = []; memory = { focus: null, selections: [] } }
    if (state.conversation && !body.again) body.conversation = state.conversation
    if (ctx && !body.again) body.context = ctx   // the next ask is read in the light of this one
    var started = Date.now()
    var beat = REDUCED ? 0 : (body.files || (body.text && !body.run) ? 1100 : 700)   // long enough to see it think, never longer
    post(ASSISTANT + '/ask', body).then(function (turn) {
      return new Promise(function (done) { setTimeout(function () { done(turn) }, Math.max(0, beat - (Date.now() - started))) })
    }).then(function (turn) {
      if (turn && turn.error) throw new Error(turn.error.message)
      cardsBox.removeAttribute('aria-busy')
      if (turn.conversation && turn.conversation !== state.conversation) { state.conversation = turn.conversation; state.turns = [] }
      var line = turn.caption || ((turn.cards || [])[0] || {}).say || ''
      state.turns.push({ id: turn.turn, conversation: turn.conversation, text: turn.text, caption: line, cards: turn.cards || [], suggestions: turn.suggestions || [], via: turn.via, rating: null })
      state.index = state.turns.length - 1
      show(true, spoken)
      if ((turn.cards || [])[0] && turn.cards[0].celebrate) celebrate(cardsBox.querySelector('snap-card'))
    }).catch(function (e) { cardsBox.removeAttribute('aria-busy'); var g = cardsBox.querySelector('.snap-ghost'); if (g) g.parentNode.remove(); speakLine(e && e.message && !/fetch/i.test(e.message) ? e.message : 'I could not reach the server. Try again in a moment.') })
  }

  // ---- the two spaces ----
  function setMode (mode) { app.dataset.mode = mode; if (mode === 'task') collapseInput(true) }
  function collapseInput (collapsed) { app.classList.toggle('is-input-open', !collapsed); openInput.setAttribute('aria-expanded', collapsed ? 'false' : 'true') }

  // insights: each number with the action it calls for; the whole tile is that action
  function glance (tiles) {
    if (!tiles || !tiles.length) return null
    var section = el('section', 'snap-home-section snap-home-section--glance')
    var row = el('div', 'snap-glance-row')
    row.style.setProperty('--cols', Math.min(tiles.length, 3))   // the count rule: the tiles fill the row
    tiles.forEach(function (t, i) {
      var tile = el('button', 'snap-stat')
      tile.type = 'button'
      tile.addEventListener('click', function () { send({ text: t.action.text, run: t.action.run }, doing(t.action.run.capability, '', t.action.text)) })
      tile.style.setProperty('--i', i)
      if (t.ring) {
        var ring = el('div', 'snap-ring')
        var r = 26, len = 2 * Math.PI * r
        ring.innerHTML = '<svg width="100%" height="100%" viewBox="0 0 64 64"><circle class="snap-ring-track" cx="32" cy="32" r="26" fill="none" stroke-width="7"/>' +
          '<circle class="snap-ring-fill" cx="32" cy="32" r="26" fill="none" stroke-width="7" stroke-dasharray="' + len.toFixed(1) + '" stroke-dashoffset="' + len.toFixed(1) + '"/></svg>'
        ring.appendChild(el('span', 'snap-ring-text', t.ring.percent + '%'))
        tile.appendChild(ring)
        setTimeout(function () { var f = ring.querySelector('.snap-ring-fill'); if (f) f.setAttribute('stroke-dashoffset', (len * (1 - t.ring.percent / 100)).toFixed(1)) }, 120)
        var text = el('div', 'snap-stat-text')
        text.appendChild(el('span', 'snap-stat-label', t.ring.label))
        text.appendChild(el('span', 'snap-stat-value', t.value + ' out'))
        text.appendChild(actionLine(t))
        tile.appendChild(text)
      } else {
        tile.appendChild(el('span', 'snap-stat-icon')).appendChild(icon(t.icon, 20))
        var text2 = el('div', 'snap-stat-text')
        text2.appendChild(el('span', 'snap-stat-label', t.label))
        var value = el('span', 'snap-stat-value', REDUCED ? t.value : 0)
        text2.appendChild(value)
        text2.appendChild(actionLine(t))
        tile.appendChild(text2)
        if (!REDUCED) countUp(value, t.value)
      }
      row.appendChild(tile)
    })
    section.appendChild(row)
    return section
  }
  function actionLine (t) { var a = el('span', 'snap-stat-action'); a.appendChild(el('span', null, t.action.text)); a.appendChild(icon('arrow-right', 14)); return a }
  function countUp (node, to) {
    var start = null
    function step (ts) { if (!start) start = ts; var p = Math.min(1, (ts - start) / 700); node.textContent = Math.round(to * (1 - Math.pow(1 - p, 3))); if (p < 1) requestAnimationFrame(step) }
    requestAnimationFrame(step)
  }

  function showHome (push) {
    setMode('home')
    state.conversation = null; state.turns = []; state.index = -1
    document.getElementById('snap-greeting').textContent = home.greeting
    speakLine(home.lines || home.message, false, (home.sections || []).some(function (s) { return s.key === 'needs' }))
    progress()
    suggestions(home.suggestions)
    cardsBox.textContent = ''
    var g = glance(home.insights)
    if (g) cardsBox.appendChild(g)
    // the sections, one under the other, each of one card template; the count rule keeps every row full:
    // a row has as many columns as cards (up to the template's most: offers 2, objects 3, shortcuts 4), the full-width
    // templates stack (3 at most); what does not fit is behind See all. An object alone becomes a feature card.
    var MOST = { offer: 2, object: 3, shortcut: 4, banner: 3, progress: 3, update: 3 }
    var STACKED = { banner: true, progress: true, update: true }
    var order = (home.insights || []).length
    ;(home.sections || []).forEach(function (s) {
      var kind = s.template || 'object'
      var shown = s.cards.slice(0, MOST[kind] || 3)
      var section = el('section', 'snap-home-section snap-home-section--' + s.key)
      section.setAttribute('aria-label', s.title)
      var head = el('div', 'snap-section-head')
      var h = el('h2', 'snap-section-title', s.title)
      if (s.key === 'needs') h.appendChild(el('span', 'snap-count', s.cards.length))
      head.appendChild(h)
      if (s.more && (s.cards.length > shown.length || kind === 'object' || kind === 'shortcut')) {
        var more = el('button', 'snap-link snap-section-more')
        more.type = 'button'
        more.appendChild(el('span', null, s.more.text)); more.appendChild(icon('arrow-right', 14))
        more.addEventListener('click', function () { if (s.more.run) send({ text: s.title, run: s.more.run }, doing(s.more.run.capability, '', s.title)); else ask(s.more.ask) })
        head.appendChild(more)
      }
      section.appendChild(head)
      var row = el('div', 'snap-grid snap-grid--' + kind)
      row.style.setProperty('--cols', STACKED[kind] ? 1 : shown.length)
      row.style.setProperty('--cols2', STACKED[kind] ? 1 : Math.min(shown.length, 2))   // tablets: two at most
      var single = kind === 'object' && shown.length === 1
      shown.forEach(function (c) { var n = card(c, s.key === 'needs', single ? 'feature' : kind); n.style.setProperty('--d', (order++ * 60) + 'ms'); row.appendChild(n) })
      section.appendChild(row)
      cardsBox.appendChild(section)
    })
    if (push) window.history.pushState({ home: true }, '', HOME + ASSISTANT)
  }

  // the task space: the latest answer (or the one you stepped back to) and only its cards
  function show (push, spoken) {
    var turn = state.turns[state.index]
    if (!turn) return showHome(false)
    setMode('task')
    ;(turn.cards || []).forEach(focusOn)
    speakLine(turn.lastLine || turn.caption || ((turn.cards[0] || {}).say) || (turn.cards.length ? '' : 'I did not find anything to show.'), spoken)
    suggestions(turn.suggestions)
    cardsBox.textContent = ''
    var row = el('div', 'snap-row snap-row--task')
    turn.cards.forEach(function (c) { row.appendChild(card(c)) })
    cardsBox.appendChild(row)
    document.getElementById('snap-position').textContent = (state.index + 1) + ' of ' + state.turns.length
    document.getElementById('snap-prev').disabled = state.index <= 0
    document.getElementById('snap-next').disabled = state.index >= state.turns.length - 1
    document.getElementById('snap-up').setAttribute('aria-pressed', turn.rating === 1 ? 'true' : 'false')
    document.getElementById('snap-down').setAttribute('aria-pressed', turn.rating === -1 ? 'true' : 'false')
    document.getElementById('snap-again').disabled = !turn.id
    if (push && turn.id) window.history.pushState({ c: turn.conversation, t: turn.id }, '', HOME + ASSISTANT + '?c=' + turn.conversation + '&t=' + turn.id)
  }

  function openConversation (conversation, turnId, push) {
    window.Snap.call('snap_conversation', { conversation: conversation }).then(function (turns) {
      if (!turns || !turns.length) return showHome(false)
      state.conversation = conversation
      state.turns = turns
      state.index = turns.length - 1
      if (turnId) turns.forEach(function (t, i) { if (t.id === Number(turnId)) state.index = i })
      show(push)
    })
  }
  function closeHistory () { historyPanel.hidden = true; historyBtn.setAttribute('aria-expanded', 'false') }

  // ---- controls ----
  function goHome () { window.Snap.call('snap_home', {}).then(function (h) { if (h) home = h; showHome(true) }) }
  document.getElementById('snap-go-home').addEventListener('click', goHome)
  document.getElementById('snap-prev').addEventListener('click', function () { if (state.index > 0) { state.index--; show(true) } })
  document.getElementById('snap-next').addEventListener('click', function () { if (state.index < state.turns.length - 1) { state.index++; show(true) } })
  document.getElementById('snap-again').addEventListener('click', function () { var t = state.turns[state.index]; if (t && t.id) ask(null, t.id) })
  ;[['snap-up', 1, 'Thanks! I will keep answering like that.'], ['snap-down', -1, 'Thanks for telling me. Want a different answer? Tap the arrows.']].forEach(function (b) {
    document.getElementById(b[0]).addEventListener('click', function () {
      var t = state.turns[state.index]
      if (!t || !t.id) return
      window.Snap.call('snap_rate', { turn: t.id, rating: b[1] }).then(function (r) {
        if (!r) return
        t.rating = b[1]
        document.getElementById('snap-up').setAttribute('aria-pressed', b[1] === 1 ? 'true' : 'false')
        document.getElementById('snap-down').setAttribute('aria-pressed', b[1] === -1 ? 'true' : 'false')
        speakLine(b[2])
      })
    })
  })
  // typing: in the task space the input opens and closes; on Home it is always there, so it takes the focus or lets go
  function toggleInput (force) {
    var compactOn = !document.getElementById('snap-compact').hidden
    if (app.dataset.mode === 'home') {
      if (document.activeElement === input && force !== true) { input.blur(); return }
      if (compactOn) window.scrollTo({ top: 0, behavior: REDUCED ? 'auto' : 'smooth' })
      input.focus({ preventScroll: compactOn })
      return
    }
    var open = force === true || !app.classList.contains('is-input-open')
    collapseInput(!open)
    if (open) {
      if (compactOn) window.scrollTo({ top: 0, behavior: REDUCED ? 'auto' : 'smooth' })
      input.focus({ preventScroll: compactOn })   // the assistant keeps its line: this may well go on with what is on screen
    } else input.blur()
  }
  openInput.addEventListener('click', function () { toggleInput() })
  document.getElementById('snap-compact-type').addEventListener('click', function () { toggleInput(true) })
  document.getElementById('snap-compact-home').addEventListener('click', goHome)
  compactLine.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: REDUCED ? 'auto' : 'smooth' }) })
  ;[[openInput, 'Type a message', KEYS.type, 'Alt+K'], [document.getElementById('snap-compact-type'), 'Type a message', KEYS.type, 'Alt+K']].forEach(function (b) {
    b[0].title = b[1] + ' (' + b[2] + ')'; b[0].setAttribute('aria-keyshortcuts', b[3])
  })
  input.placeholder = 'Ask or search for anything  ·  ' + KEYS.type + ' to type, ' + KEYS.talk + ' to talk'
  document.addEventListener('keydown', function (e) {
    var inField = /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName) || e.target.isContentEditable
    if (e.altKey && !e.ctrlKey && !e.metaKey && e.code === 'KeyK') { e.preventDefault(); toggleInput() }
    else if (e.altKey && !e.ctrlKey && !e.metaKey && e.code === 'KeyM') { e.preventDefault(); toggleTalk() }
    else if (e.key === '/' && !inField && !e.ctrlKey && !e.metaKey && !e.altKey) { e.preventDefault(); toggleInput(true) }
    else if (e.key === 'Escape' && e.target === input && app.dataset.mode === 'task') { collapseInput(true); input.blur() }
  })
  historyBtn.addEventListener('click', function () {
    var open = historyPanel.hidden
    historyPanel.hidden = !open
    historyBtn.setAttribute('aria-expanded', open ? 'true' : 'false')
    if (!open) return
    var list = document.getElementById('snap-history-list')
    list.textContent = ''
    state.turns.forEach(function (t, i) {
      var b = el('button', 'snap-history-item' + (i === state.index ? ' is-current' : ''))
      b.type = 'button'
      b.appendChild(icon('message-circle', 15)); b.appendChild(el('span', null, t.text))
      b.addEventListener('click', function () { state.index = i; closeHistory(); show(true) })
      list.appendChild(el('li')).appendChild(b)
    })
    window.Snap.call('snap_history', {}).then(function (all) {
      ;(all || []).filter(function (h) { return h.conversation !== state.conversation }).forEach(function (h) {
        var b = el('button', 'snap-history-item snap-history-item--other')
        b.type = 'button'
        b.appendChild(icon('history', 15)); b.appendChild(el('span', null, h.title + ' · ' + h.turns + ' answer' + (h.turns > 1 ? 's' : '')))
        b.addEventListener('click', function () { closeHistory(); openConversation(h.conversation, null, true) })
        list.appendChild(el('li')).appendChild(b)
      })
    })
  })
  document.getElementById('snap-history-close').addEventListener('click', closeHistory)
  form.addEventListener('submit', function (event) {
    event.preventDefault()
    var text = input.value.trim()
    if (!text && !attached.length) return
    input.value = ''
    if (!attached.length) return ask(text)
    var files = attached.map(function (f) { return { name: f.name, type: f.type, data: f.data } })
    var names = attached.map(function (f) { return f.name })
    attached = []; drawAttached()
    send({ text: text, files: files }, ['Reading ' + (names.length > 1 ? 'your ' + names.length + ' files' : '"' + names[0] + '"') + '…', 'Looking at what is in ' + (names.length > 1 ? 'them' : 'it') + '…', 'Matching it with the ' + APP + '…', 'Preparing the card…'])
  })

  // ---- the +: files the assistant reads (pictures, PDFs, text), 3 at most, 5 MB in all ----
  var attached = []
  var filesInput = document.getElementById('snap-files')
  var attachedBox = document.getElementById('snap-attached')
  var TYPES = { png: 'image/png', jpg: 'image/jpeg', jpeg: 'image/jpeg', gif: 'image/gif', webp: 'image/webp', pdf: 'application/pdf', txt: 'text/plain', csv: 'text/csv', md: 'text/markdown' }
  function drawAttached () {
    attachedBox.textContent = ''
    attachedBox.hidden = !attached.length
    attached.forEach(function (f, i) {
      var chip = el('span', 'snap-file')
      chip.appendChild(icon(/^image\//.test(f.type) ? 'image' : (f.type === 'application/pdf' ? 'file-text' : 'file'), 15))
      chip.appendChild(el('span', 'snap-file-name', f.name))
      var x = el('button', 'snap-file-remove')
      x.type = 'button'; x.setAttribute('aria-label', 'Remove ' + f.name)
      x.appendChild(icon('x', 14))
      x.addEventListener('click', function () { attached.splice(i, 1); drawAttached(); speakLine(attached.length ? 'Removed. ' + attached.length + ' file' + (attached.length > 1 ? 's' : '') + ' left.' : 'Removed. Anything else?') })
      chip.appendChild(x)
      attachedBox.appendChild(chip)
    })
  }
  document.getElementById('snap-attach').addEventListener('click', function () { filesInput.click() })
  filesInput.addEventListener('change', function () {
    var picked = Array.prototype.slice.call(filesInput.files || [])
    filesInput.value = ''
    var total = attached.reduce(function (n, f) { return n + f.size }, 0)
    var refused = null
    var reads = picked.map(function (file) {
      var type = file.type || TYPES[(file.name.split('.').pop() || '').toLowerCase()] || ''
      if (Object.values(TYPES).indexOf(type) < 0) { refused = 'I can read pictures, PDFs and text files: "' + file.name + '" is not one of them.'; return null }
      if (attached.length >= 3) { refused = 'Three files at a time is the most I can read.'; return null }
      if (total + file.size > 5242880) { refused = 'That is more than 5 MB together: try fewer or smaller files.'; return null }
      total += file.size
      var slot = { name: file.name, type: type, size: file.size, data: null }
      attached.push(slot)
      return new Promise(function (done) {
        var reader = new FileReader()
        reader.onload = function () { slot.data = String(reader.result).split(',')[1] || ''; done() }
        reader.onerror = function () { attached.splice(attached.indexOf(slot), 1); done() }
        reader.readAsDataURL(file)
      })
    }).filter(Boolean)
    Promise.all(reads).then(function () {
      drawAttached()
      if (refused && !reads.length) return speakLine(refused)
      var n = attached.length
      speakLine((refused ? refused + ' ' : '') + (n === 1 ? 'Got "' + attached[0].name + '". ' : 'Got ' + n + ' files. ') + 'Tell me what to do with ' + (n > 1 ? 'them' : 'it') + ', or just send.')
      if (app.dataset.mode === 'task') collapseInput(false)
      input.focus()
    })
  })
  document.addEventListener('snap:shortcut', function () { window.Snap.call('snap_home', {}).then(function (h) { if (h) home = h }) })

  // voice: where the browser can listen
  // the microphone: in the input bar on Home, and in the task space's controls next to the keyboard
  var mics = [document.getElementById('snap-mic'), document.getElementById('snap-talk'), document.getElementById('snap-compact-talk')].filter(Boolean)
  var Recognition = window.SpeechRecognition || window.webkitSpeechRecognition
  var toggleTalk = function () { speakLine('I cannot listen in this browser. Type instead (' + KEYS.type + ').'); toggleInput(true) }
  if (Recognition && mics.length) {
    var listening = null
    var on = function (yes) { mics.forEach(function (m) { m.classList.toggle('is-on', yes); m.setAttribute('aria-pressed', yes ? 'true' : 'false') }) }
    toggleTalk = function () {
        if (listening) { listening.stop(); return }
        var before = said, heard = false
        listening = new Recognition()
        listening.lang = document.documentElement.lang || 'en-US'
        listening.interimResults = false
        on(true)
        speakLine('I am listening…')
        listening.onresult = function (e) { heard = true; ask(e.results[0][0].transcript, null, true) }
        // stopped without a word (turned off, or silence): back to the last answer, not "listening"
        listening.onend = function () { listening = null; on(false); if (!heard) speakLine(before) }
        listening.start()
    }
    mics.forEach(function (mic) {
      mic.hidden = false
      mic.title = 'Talk to me (' + KEYS.talk + ')'
      mic.setAttribute('aria-keyshortcuts', 'Alt+M')
      mic.addEventListener('click', toggleTalk)
    })
  }

  window.addEventListener('popstate', function (e) { var s = e.state || {}; if (s.c) openConversation(s.c, s.t, false); else showHome(false) })

  // mobile: the conversation is pinned at the bottom; the page keeps room for it
  var convo = document.getElementById('snap-convo')
  if ('ResizeObserver' in window) new ResizeObserver(function () { app.style.setProperty('--snap-convo-h', (convo.offsetHeight + 24) + 'px') }).observe(convo)

  // the compact conversation: pinned under the top bar once the assistant's line scrolls under it (not on phones,
  // where the conversation is at the bottom already)
  var compact = document.getElementById('snap-compact')
  if ('IntersectionObserver' in window) {
    var navHeight = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--cq-nav-height'), 10) || 56
    new IntersectionObserver(function (entries) {
      var away = !entries[0].isIntersecting && entries[0].boundingClientRect.top < navHeight
      compact.hidden = !away || window.innerWidth <= 640
      app.classList.toggle('is-compact', !compact.hidden)
    }, { rootMargin: '-' + navHeight + 'px 0px 0px 0px' }).observe(message)
  }

  applyIcons(document)
  // the page opens with the assistant waking up: its mark pops in, then the greeting is typed
  var hello = document.querySelector('.snap-hello .snap-mark')
  if (hello && !REDUCED) {
    hello.classList.add('is-waking')
    hello.addEventListener('animationend', function () { hello.classList.remove('is-waking') }, { once: true })
    // a few sparkles as it wakes: the assistant says hello
    setTimeout(function () {
      if (typeof window.confetti !== 'function' || app.dataset.mode !== 'home') return
      var r = hello.getBoundingClientRect()
      window.confetti({ particleCount: 26, spread: 360, startVelocity: 14, gravity: 0.5, ticks: 90, scalar: 0.65, shapes: ['star'],
        origin: { x: (r.left + r.width / 2) / window.innerWidth, y: (r.top + r.height / 2) / window.innerHeight },
        colors: ['#7c5cff', '#c0377a', '#f59e0b', '#5b3fd3'], disableForReducedMotion: true })
    }, 420)
  }
  var params = new URLSearchParams(window.location.search)
  if (params.get('c')) openConversation(params.get('c'), params.get('t'), false)
  else showHome(false)
})()
