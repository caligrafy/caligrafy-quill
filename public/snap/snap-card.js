/*
 * Snap Cards renderer (prototype, redesigned after the 2026-11-14 reviews): <snap-card> draws a card from its JSON and
 * follows the chain. Families and types: Ask (Form, Choice, Confirm), Show (Answer, List), Go (Shortcut),
 * Track (Task), Offer (Suggestion, Routine). People never see them: they are for makers and marketing.
 *
 * Sizes: size="tile" is Home's minimal card (an icon, a title, one line; a click asks the page to open it in the task
 * space with `snap:open`); the default is the full card. In the task space the card is "voiced": the assistant's line
 * above it already says what it is about, so the card leaves out its title, lead and instructions and reads as the rest
 * of the sentence.
 *
 * Every action on a card leads to a reply from the assistant: the next card carries `say` (written by the server), and
 * actions that stay on the page (Cancel, Edit) say their own line. Both reach the page as a `snap:say` event; a moment
 * worth celebrating as `snap:celebrate`. Icons are Lucide (window.lucide); the DOM is built with textContent only.
 * The page gives <meta name="snap-home"> (the site's address, ending with /) and <meta name="snap-csrf">.
 */
(function () {
  'use strict'
  var meta = function (name) { var m = document.querySelector('meta[name="' + name + '"]'); return m ? m.content : '' }
  var HOME = meta('snap-home') || '/'
  var CARDS = meta('snap-cards') || 'snap'   // Route::snap(): where the cards are served ('snap' by default)
  var REDUCED = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches

  function el (tag, cls, text) {
    var node = document.createElement(tag)
    if (cls) node.className = cls
    if (text !== undefined && text !== null) node.textContent = String(text)
    return node
  }

  // a Lucide icon by its name ("book-open"); an empty span when the library is not there
  function icon (name, size) {
    var span = el('span', 'snap-i')
    span.setAttribute('aria-hidden', 'true')
    var lib = window.lucide
    if (!lib || !name) return span
    var pascal = String(name).replace(/(^|-)(\w)/g, function (m, a, b) { return b.toUpperCase() })
    var node = (lib.icons && lib.icons[pascal]) || lib[pascal]
    if (!node || !lib.createElement) return span
    var svg = lib.createElement(node)
    svg.setAttribute('width', size || 18)
    svg.setAttribute('height', size || 18)
    svg.setAttribute('stroke-width', '1.8')
    span.appendChild(svg)
    return span
  }

  // a button with an icon and a label (or an icon only, labelled for screen readers)
  function button (cls, label, iconName, onlyIcon) {
    var b = el('button', cls)
    b.type = 'button'
    if (iconName) b.appendChild(icon(iconName, 16))
    if (onlyIcon) { b.setAttribute('aria-label', label); b.title = label } else b.appendChild(el('span', null, label))
    return b
  }

  function initials (name) {
    return String(name || '?').split(/[\s.@]+/).filter(Boolean).slice(0, 2).map(function (w) { return w[0].toUpperCase() }).join('')
  }
  function avatar (name) {
    var a = el('span', 'snap-avatar', initials(name))
    var hue = 0
    for (var i = 0; i < String(name).length; i++) hue = (hue * 31 + String(name).charCodeAt(i)) % 360
    a.style.setProperty('--hue', hue)
    a.setAttribute('aria-hidden', 'true')
    return a
  }

  function address (path) { return HOME + String(path || '').replace(/^\//, '') }
  function notice (message) { return { snap: 1, family: 'show', type: 'answer', variant: 'notice', tone: 'error', title: 'Snap', heading: message, fields: [], glance: message, say: message, icon: 'triangle-alert' } }
  function headers () { return { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': meta('snap-csrf') } }

  // a card is sent: snap_run (Route::action) answers with the next card, refusals included
  function run (capability, inputs, confirm) {
    return fetch(address(CARDS + '/run'), {
      method: 'POST', credentials: 'same-origin', headers: headers(),
      body: JSON.stringify(confirm ? { capability: capability, inputs: inputs || {}, confirm: confirm } : { capability: capability, inputs: inputs || {} })
    }).then(function (r) { return r.json().catch(function () { return notice('Something went wrong (' + r.status + ')') }) })
      .then(function (card) { return card && card.snap ? card : notice((card && card.error && card.error.message) || 'Something went wrong') })
      .catch(function () { return notice('The server could not be reached') })
  }

  // a plain capability through the capabilities endpoint: its data, or null
  function call (capability, input) {
    return fetch(address(CARDS + '/call/' + capability), { method: 'POST', credentials: 'same-origin', headers: headers(), body: JSON.stringify(input || {}) })
      .then(function (r) { return r.json() }).then(function (a) { return a && a.data !== undefined ? a.data : null }).catch(function () { return null })
  }

  // the Ask card of a capability (snap_card); asForm: the whole form even when one pick is missing
  function ask (capability, values, asForm) {
    return call('snap_card', asForm ? { capability: capability, values: values || {}, as: 'form' } : { capability: capability, values: values || {} })
      .then(function (card) { return card && card.snap ? card : notice('No card') })
  }

  // what a button says while its action runs ("Adding…"), from the capability's verb
  var CHANGES = /^(create|update|delete|lend|return|remind|send|register|renew)_/
  function verbing (capability, confirm) {
    if (!confirm && CHANGES.test(String(capability))) return 'Preparing…'   // only the confirmed click changes anything
    var verb = String(capability || '').split('_')[0]
    return { create: 'Adding…', update: 'Saving…', delete: 'Deleting…', lend: 'Lending…', return: 'Recording…', remind: 'Sending…', send: 'Sending…',
      get: 'Opening…', list: 'Opening…', search: 'Searching…', snap: 'Saving…' }[verb] || 'Working…'
  }
  function wait (ms) { return new Promise(function (done) { setTimeout(done, ms) }) }
  var BEAT = REDUCED ? 0 : 550   // an answer never lands before the person saw it being worked on
  function clock (seconds) { var m = Math.floor(seconds / 60), s = seconds % 60; return m + ':' + (s < 10 ? '0' : '') + s }

  // what the assistant says, and what deserves a celebration: the page listens
  function say (node, text, card) { if (text) node.dispatchEvent(new CustomEvent('snap:say', { bubbles: true, detail: { text: text, card: card || null } })) }

  class SnapCard extends HTMLElement {
    connectedCallback () {
      if (!this._card && this.hasAttribute('data-card')) {
        try { this.card = JSON.parse(this.getAttribute('data-card')) } catch (e) { this.card = notice('This card could not be read') }
      }
    }
    disconnectedCallback () { clearTimeout(this._poll) }
    set card (card) { this._history = this._history || []; this._card = card; this.render(true) }
    get card () { return this._card }

    // the chain: the next card takes this one's place, with motion; the assistant says its line
    go (next) {
      var self = this
      this._history.push(this._card)
      var swap = function () {
        self._card = next
        self.render(true)
        var heading = self.querySelector('.snap-title')
        if (heading) { heading.setAttribute('tabindex', '-1'); heading.focus({ preventScroll: true }) }
        var top = self.getBoundingClientRect().top
        if (!self.voiced && (top < 0 || top > window.innerHeight * 0.7)) self.scrollIntoView({ block: 'start', behavior: REDUCED ? 'auto' : 'smooth' })   // voiced: the page keeps the assistant's line in view
        say(self, next.say, next)
        if (next.celebrate) self.dispatchEvent(new CustomEvent('snap:celebrate', { bubbles: true, detail: { card: next } }))
        self.dispatchEvent(new CustomEvent('snap:next', { bubbles: true, detail: next }))
      }
      var article = this.querySelector('.snap')
      if (!article || REDUCED) return swap()
      article.classList.add('is-leaving')
      setTimeout(swap, 180)
    }
    back () {
      if (!this._history.length) return
      this._card = this._history.pop()
      this.render(true)
      say(this, 'Back to ' + (this._card.title || 'the card before') + '.')
    }

    // busy: the button that was pressed spins and says what it is doing; the rest of the card waits
    busy (on, capability, confirm) {
      this.classList.toggle('is-busy', !!on)
      this.setAttribute('aria-busy', on ? 'true' : 'false')
      var b = this._pressed && this.contains(this._pressed) ? this._pressed : this.querySelector('button[type=submit]')
      if (on && b && b.classList.contains('snap-btn')) {
        b.classList.add('is-loading')
        b.insertBefore(el('span', 'snap-i snap-spin'), b.firstChild)
        var label = b.querySelector('span:not(.snap-i)')
        if (label) label.textContent = verbing(capability, confirm)
      }
    }

    // runs an action of this card and goes on with what comes back
    act (capability, inputs, confirm, label) {
      var self = this
      self.busy(true, capability, confirm)
      // the page says what is being done ("Lending "Kindred"…") while it is done
      self.dispatchEvent(new CustomEvent('snap:busy', { bubbles: true, detail: { capability: capability, inputs: inputs || {}, confirm: !!confirm, card: self._card, label: label || '' } }))
      var started = Date.now()
      return run(capability, inputs, confirm).then(function (next) {
        return wait(Math.max(0, BEAT - (Date.now() - started))).then(function () { self.busy(false); self.go(next) })
      })
    }

    get voiced () { return this.hasAttribute('voiced') }

    render (enter) {
      var c = this._card
      this.textContent = ''
      if (!c) return
      var size = this.getAttribute('size') || 'card'
      if (['banner', 'offer', 'progress', 'object', 'feature', 'update', 'shortcut'].indexOf(size) >= 0) return this.template(c, size, enter)
      var voiced = this.voiced
      if (voiced && (c.variant === 'notice' || (c.type === 'list' && !(c.rows || []).length))) { this.hidden = true; return }   // the assistant said it, and its ways out are the suggestions
      this.hidden = false
      var card = el('article', 'snap snap--' + c.family + ' snap--' + c.type + (c.variant ? ' snap--' + c.variant : '') + ' snap-size--' + size + (voiced ? ' is-voiced' : '') + (enter && !REDUCED ? ' is-entering' : ''))
      if (!voiced) {
        var head = el('header', 'snap-head')
        head.appendChild(el('span', 'snap-tile snap-tile--' + (c.variant === 'alert' ? 'alert' : c.family))).appendChild(icon(c.variant === 'alert' ? 'bell-ring' : (c.icon || 'sparkles'), 20))
        var titles = el('div', 'snap-titles')
        titles.appendChild(el('h3', 'snap-title', c.title))
        head.appendChild(titles)
        card.appendChild(head)
      } else {
        card.appendChild(el('h3', 'visually-hidden snap-title', c.title))   // for screen readers and focus
      }
      if (size === 'glance') {
        card.appendChild(el('p', 'snap-glance', c.glance || ''))
        if (c.url) { var open = el('a', 'snap-link', 'Open'); open.href = address(c.url); card.appendChild(open) }
        this.appendChild(card)
        return
      }
      if (!voiced && c.lead && c.family === 'ask' && c.type !== 'confirm') card.appendChild(el('p', 'snap-lead', c.lead))
      var body = el('div', 'snap-body')
      card.appendChild(body)
      // a refusal's general message, unless the fields say what is wrong themselves
      var fieldErrors = c.errors && Object.keys(c.errors).length
      if (c.message && c.type !== 'confirm' && !fieldErrors && !voiced) body.appendChild(el('p', 'snap-alert snap-alert--error', c.message))
      var draw = { form: this.form, choice: this.choice, confirm: this.confirm, answer: this.answer, list: this.list, shortcut: this.shortcut, task: this.task, suggestion: this.suggestion, routine: this.routine }[c.type]
      if (draw) draw.call(this, c, body)
      var foot = this.foot(c)
      if (foot.childNodes.length) card.appendChild(foot)
      this.appendChild(card)
      if (c.attention) card.classList.add('is-calling')
      var self = this
      card.addEventListener('click', function (e) { var b = e.target.closest('button'); if (b) self._pressed = b }, true)   // the button that will spin
    }

    // ---- Home's card templates (the card grammar): each kind of card has one shape, chosen by what the person does
    //   banner    act now      full width, tinted by severity, its one action inline, a severity tag
    //   offer     decide       half: the question, why, yes and not now; no tag
    //   progress  watch        full-width row: what it is doing now, its time, a live bar, a status tag
    //   object    browse       a third (a feature card when alone): why it is here, its name, two facts; opens
    //   update    revisit      full-width row: the result in a line, when, Done or Failed, a chevron
    //   shortcut  go           a quarter: icon and name; no tag, no button
    // A click anywhere on a card opens it in the task space (snap:open); its one action runs (snap:go).
    template (c, kind, enter) {
      var self = this
      var open = function () { self.dispatchEvent(new CustomEvent('snap:open', { bubbles: true, detail: c })) }
      var go = function (text, run) { self.dispatchEvent(new CustomEvent('snap:go', { bubbles: true, detail: { text: text, run: run } })) }
      var t = el('div', 'snap-t snap-t--' + kind + (enter && !REDUCED ? ' is-entering' : '') + (c.attention ? ' is-calling' : ''))
      t.setAttribute('role', 'button')
      t.tabIndex = 0
      t.setAttribute('aria-label', c.title)
      t.addEventListener('click', function (e) { if (!e.target.closest('button')) open() })
      t.addEventListener('keydown', function (e) { if ((e.key === 'Enter' || e.key === ' ') && e.target === t) { e.preventDefault(); open() } })
      var tag = function (text, tone) { return el('span', 'snap-tag snap-tag--' + tone, text) }
      var action = function (label, primary, fn) {
        var b = el('button', 'snap-btn snap-btn--sm ' + (primary ? 'snap-btn--primary' : 'snap-btn--quiet'), label)
        b.type = 'button'
        b.addEventListener('click', function (e) { e.stopPropagation(); fn(b) })
        return b
      }
      var lines = function (box, title, sub) { var d = el('div', 'snap-t-text'); d.appendChild(el('span', 'snap-t-title', title)); if (sub) d.appendChild(el('span', 'snap-t-sub', sub)); box.appendChild(d); return d }
      var mark = function (name, tone) { var m = el('span', 'snap-t-icon' + (tone ? ' snap-t-icon--' + tone : '')); m.appendChild(icon(name, 18)); return m }

      if (kind === 'banner') {
        // act now: an alert, a task waiting on the person, a change to confirm
        var tone = c.type === 'task' ? (c.status === 'failed' ? 'danger' : 'accent') : (c.variant === 'alert' ? (c.severity || 'danger') : 'warning')
        t.classList.add('snap-t--' + tone)
        t.appendChild(mark(c.variant === 'alert' ? 'bell-ring' : (c.type === 'task' ? (c.status === 'failed' ? 'triangle-alert' : 'message-circle-question') : 'list-checks'), tone))
        var sub = c.variant === 'alert' ? (c.items || []).join(' · ') : (c.type === 'task' ? c.statusLabel : (c.reason || ''))
        lines(t, c.variant === 'alert' ? (c.question || c.title) : (c.question && c.type !== 'task' ? c.question : c.title), sub)
        t.appendChild(tag(c.tag || (c.type === 'task' ? c.statusLabel : 'To confirm'), tone))
        if (c.variant === 'alert' && c.target) t.appendChild(action(c.yes || 'Take care of it', true, function () { go(c.yes || c.title, c.target) }))
        else t.appendChild(action(c.type === 'task' ? (c.status === 'failed' ? 'See why' : 'Answer') : 'Review', true, open))
      } else if (kind === 'offer') {
        // decide: a proposal, with yes and not now (a routine is set up in the task space, where its level is chosen)
        lines(t, c.question || c.title, c.reason ? (c.type === 'routine' ? c.reason : c.reason) : '')
        var row = el('div', 'snap-t-actions')
        row.appendChild(action(c.type === 'routine' ? 'Set it up' : (c.yes || 'Yes, do it'), true, function () { if (c.type === 'routine' || !c.target) open(); else go(c.question || c.title, c.target) }))
        if (c.fingerprint) row.appendChild(action('Not now', false, function () {
          call('snap_dismiss', { fingerprint: c.fingerprint, kind: 'today' }).then(function () {
            say(self, 'Okay, I will ask again tomorrow.')
            t.classList.add('is-leaving')
            setTimeout(function () { self.dispatchEvent(new CustomEvent('snap:gone', { bubbles: true })); self.remove() }, REDUCED ? 0 : 220)
          })
        }))
        t.appendChild(row)
      } else if (kind === 'progress') {
        // watch: what it is doing now, its time, a live bar
        t.appendChild(mark(c.icon || 'activity'))
        var text = lines(t, c.title, (c.working && c.working[0]) || c.statusLabel)
        var bar = el('span', 'snap-progress snap-progress--' + c.status); bar.appendChild(el('span', 'snap-progress-fill')); text.appendChild(bar)
        this._since = this._since || Date.now()
        var timer = el('span', 'snap-t-time', clock(Math.floor((Date.now() - this._since) / 1000)))
        t.appendChild(timer)
        t.appendChild(tag(c.status === 'queued' ? 'Waiting' : 'Running', 'warning'))
        var phrase = text.querySelector('.snap-t-sub'); var words = c.working && c.working.length ? c.working : [c.statusLabel]; var k = 0
        clearInterval(this._tick)
        this._tick = setInterval(function () {
          if (!self.isConnected) return clearInterval(self._tick)
          var secs = Math.floor((Date.now() - self._since) / 1000)
          timer.textContent = clock(secs)
          if (c.status === 'running' && secs % 3 === 0 && phrase) { phrase.textContent = words[++k % words.length]; phrase.classList.remove('is-swap'); void phrase.offsetWidth; phrase.classList.add('is-swap') }
        }, 1000)
        clearTimeout(this._poll)
        this._poll = setTimeout(function () {
          call('snap_task', { id: c.id }).then(function (next) {
            if (!next || !next.snap || !self.isConnected) return
            next.key = c.key
            if (next.live) { self._card = next; return self.render(false) }
            // done: the row turns into its update, with a moment
            clearInterval(self._tick)
            self.setAttribute('size', 'update')
            self._card = next
            self.render(false)
            var u = self.querySelector('.snap-t')
            if (u && !REDUCED) u.classList.add('is-finished')
            say(self, next.say || (next.title + ' is ready.'))
            if (next.celebrate) self.dispatchEvent(new CustomEvent('snap:celebrate', { bubbles: true, detail: { card: next } }))
          })
        }, 3000)
      } else if (kind === 'object' || kind === 'feature') {
        // browse: why it is here, its name, two facts; alone, a feature card with room to breathe
        if (kind === 'feature') t.appendChild(mark(c.icon || 'book-open'))
        var box = el('div', 'snap-t-text')
        if (c.reason) box.appendChild(tag(c.reason, c.personal ? 'accent' : (c.reason === 'New' ? 'success' : 'neutral')))
        box.appendChild(el('span', 'snap-t-title', c.title))
        if (c.glance) box.appendChild(el('span', 'snap-t-sub', c.glance))
        t.appendChild(box)
        if (kind === 'feature') t.appendChild(el('span', 'snap-t-chevron')).appendChild(icon('chevron-right', 18))
      } else if (kind === 'update') {
        // revisit: the result in a line, when, a Done or Failed tag
        var ok = c.status !== 'failed'
        t.appendChild(mark(ok ? 'check' : 'triangle-alert', ok ? 'success' : 'danger'))
        lines(t, c.title, (c.result && c.result.glance) || '')
        if (c.ago) t.appendChild(el('span', 'snap-t-time', c.ago))
        t.appendChild(tag(ok ? 'Done' : 'Failed', ok ? 'success' : 'danger'))
        t.appendChild(el('span', 'snap-t-chevron')).appendChild(icon('chevron-right', 18))
      } else {
        // go: icon and name, nothing else
        t.appendChild(mark(c.icon || 'arrow-right'))
        t.appendChild(el('span', 'snap-t-title', c.title))
      }
      this.appendChild(t)
    }

    // ---- Ask: Form, in three zones: what you said, what is still needed, the optional details ----
    form (c, body) {
      var self = this
      var form = el('form', 'snap-form')
      form.noValidate = true
      var fields = c.fields || []
      var by = function (state) { return fields.filter(function (f) { return f.state === state }) }
      var said = by('said').concat(by('current'))
      var needed = by('needed')
      var optional = by('optional')

      function control (f, focus) {
        var id = 'snap-' + c.capability + '-' + f.name + '-' + Math.random().toString(36).slice(2, 7)
        var field = el('div', 'snap-field' + (f.state === 'needed' ? ' is-needed' : ''))
        field.dataset.field = f.name
        var label = el('label', 'snap-label')
        label.htmlFor = id
        label.appendChild(icon(f.icon, 15))
        label.appendChild(el('span', null, f.label))
        if (!f.required && f.state !== 'needed') label.appendChild(el('span', 'snap-optional', 'optional'))
        field.appendChild(label)
        var input
        if (f.widget === 'choice' || f.widget === 'select') {
          input = el('select', 'snap-input')
          input.appendChild(el('option', null, f.required ? 'Choose…' : '(none)')).value = ''
          ;(f.options || []).forEach(function (o) {
            var option = el('option', null, o.label + (o.meta ? ' · ' + o.meta : ''))
            option.value = o.value
            if (String(o.value) === String(f.value)) option.selected = true
            input.appendChild(option)
          })
        } else if (f.widget === 'textarea') {
          input = el('textarea', 'snap-input')
          input.rows = 3
          if (f.value !== null && f.value !== undefined) input.value = f.value
        } else if (f.widget === 'checkbox') {
          input = el('input', 'snap-check')
          input.type = 'checkbox'
          input.checked = !!f.value
        } else {
          input = el('input', 'snap-input')
          input.type = { number: 'number', email: 'email', url: 'url', date: 'date', password: 'password', file: 'url' }[f.widget] || 'text'
          if (f.value !== null && f.value !== undefined) input.value = f.value
          if (f.min !== undefined) input.min = f.min
          if (f.max !== undefined) input.max = f.max
          if (f.maxlength !== undefined) input.maxLength = f.maxlength
          if (f.help && input.type === 'text') input.placeholder = f.help
        }
        input.id = id
        input.name = f.name
        if (f.required) input.setAttribute('aria-required', 'true')
        field.appendChild(input)
        if (f.help && !(input.placeholder && input.placeholder === f.help)) {
          var help = el('p', 'snap-help', f.help); help.id = id + '-help'; field.appendChild(help); input.setAttribute('aria-describedby', help.id)
        }
        var error = c.errors && c.errors[f.name]
        if (error) {
          var e = el('p', 'snap-error', error)
          e.id = id + '-error'
          field.appendChild(e)
          field.classList.add('has-error')
          input.setAttribute('aria-invalid', 'true')
          input.setAttribute('aria-describedby', ((input.getAttribute('aria-describedby') || '') + ' ' + e.id).trim())
        }
        if (focus || error) setTimeout(function () { if (self.isConnected && (document.activeElement === document.body || !document.activeElement || error)) { input.focus({ preventScroll: true }); if (error && input.select) input.select() } }, 350)
        return field
      }

      // what you said, then the record as it is now: read-only, each with Edit in place
      ;[['said', 'You said'], ['current', 'Now']].forEach(function (zone) {
        var rows = by(zone[0])
        if (!rows.length) return
        form.appendChild(el('p', 'snap-zone', zone[1]))
        var given = el('div', 'snap-details')
        rows.forEach(function (f) {
          var row = self.detail(f.icon, f.label, f.shown)
          var edit = button('snap-icon-btn', 'Edit ' + f.label, 'pencil', true)
          edit.addEventListener('click', function () {
            var open = control(f, false)
            row.replaceWith(open)
            var input = open.querySelector('input, select, textarea')
            if (input) { input.focus(); if (input.select) input.select() }   // typing replaces it
            say(self, 'Sure, change the ' + f.label.toLowerCase() + ' here.')
          })
          row.appendChild(edit)
          given.appendChild(row)
        })
        form.appendChild(given)
      })

      if (needed.length) {
        if (!self.voiced) form.appendChild(el('p', 'snap-zone snap-zone--needed', said.length ? 'Still needed' : 'What I need'))
        else if (said.length) form.appendChild(el('hr', 'snap-sep'))
        needed.forEach(function (f, i) { form.appendChild(control(f, i === 0)) })
      }

      if (optional.length) {
        var more = el('details', 'snap-more')
        var summary = el('summary')
        summary.appendChild(icon('plus', 15))
        summary.appendChild(el('span', null, 'Add details (' + optional.length + ' optional)'))
        more.appendChild(summary)
        if (self.voiced) more.classList.add('is-by-suggestion')   // the suggestions above offer each one: no second way in
        optional.forEach(function (f) { more.appendChild(control(f, false)) })
        more.addEventListener('toggle', function () { if (more.open) say(self, 'Add what you like; all of these are optional.') })
        form.appendChild(more)
        this._more = more
      }

      var submit = el('button', 'snap-btn snap-btn--primary')
      submit.type = 'submit'
      submit.appendChild(el('span', null, c.submit || 'Go'))
      submit.appendChild(icon('arrow-right', 16))
      form.appendChild(submit)
      form.addEventListener('submit', function (event) {
        event.preventDefault()
        var inputs = {}
        fields.forEach(function (f) {
          var node = form.elements[f.name]
          if (node && node.nodeType === 1) {
            if (f.widget === 'checkbox') inputs[f.name] = node.checked
            else if (node.value !== '') inputs[f.name] = node.value
          } else if ((f.state === 'said' || f.state === 'current') && f.value !== null && f.value !== undefined && f.value !== '') {
            inputs[f.name] = f.value
          }
        })
        self.act(c.capability, inputs)
      })
      body.appendChild(form)
    }

    // opens one optional detail of this form (a suggestion "Add summary" lands here)
    reveal (name) {
      if (!this._more) return false
      var field = this._more.querySelector('[data-field="' + name + '"]')
      if (!field) return false
      this._more.open = true
      field.classList.add('is-calling')
      var input = field.querySelector('input, select, textarea')
      if (input) input.focus()
      field.scrollIntoView({ block: 'center', behavior: REDUCED ? 'auto' : 'smooth' })
      return true
    }

    // one row of details: an icon, a label, a value
    detail (iconName, label, value) {
      var row = el('div', 'snap-detail')
      row.appendChild(el('span', 'snap-detail-icon')).appendChild(icon(iconName || 'circle-dot', 16))
      var text = el('div', 'snap-detail-text')
      text.appendChild(el('span', 'snap-detail-label', label))
      text.appendChild(el('span', 'snap-detail-value', value))
      row.appendChild(text)
      return row
    }

    // ---- Ask: Choice: one pick, then the card runs ----
    choice (c, body) {
      var self = this
      if ((c.said || []).length) {
        body.appendChild(el('p', 'snap-zone', 'You said'))
        var given = el('div', 'snap-details')
        c.said.forEach(function (f) { given.appendChild(self.detail(null, f.label, f.shown)) })
        var edit = button('snap-icon-btn', 'Edit what you said', 'pencil', true)
        edit.addEventListener('click', function () { self.busy(true); ask(c.capability, c.fixed || {}, true).then(function (next) { self.busy(false); self.go(next) }) })
        given.lastChild.appendChild(edit)
        body.appendChild(given)
      }
      if (!self.voiced) body.appendChild(el('p', 'snap-zone snap-zone--needed', 'Which ' + String(c.label || 'one').toLowerCase() + '?'))
      var list = el('ul', 'snap-options')
      ;(c.options || []).forEach(function (o, i) {
        var item = el('li')
        item.style.setProperty('--i', i)
        var b = el('button', 'snap-option')
        b.type = 'button'
        b.appendChild(avatar(o.label))
        var text = el('span', 'snap-option-text')
        text.appendChild(el('span', 'snap-option-label', o.label))
        if (o.meta) text.appendChild(el('span', 'snap-option-meta', o.meta))
        b.appendChild(text)
        b.appendChild(icon('chevron-right', 16))
        b.addEventListener('click', function () {
          var inputs = Object.assign({}, c.fixed || {})
          inputs[c.field] = o.value
          self.act(c.capability, inputs, null, o.label)
        })
        item.appendChild(b)
        list.appendChild(item)
      })
      if (!(c.options || []).length) list.appendChild(el('li', 'snap-empty', 'Nothing to choose from.'))
      body.appendChild(list)
    }

    // ---- Ask: Confirm: what will change, then the person's yes ----
    confirm (c, body) {
      var self = this
      if (!self.voiced) body.appendChild(el('p', 'snap-question', c.question))
      if ((c.changes || []).length) {
        var list = el('div', 'snap-details snap-changes')
        c.changes.forEach(function (line) {
          var row = self.detail(line.icon || 'circle-dot', line.label, '')
          var value = row.querySelector('.snap-detail-value')
          if (line.before !== undefined) { value.appendChild(el('del', 'snap-before', line.before)); value.appendChild(icon('arrow-right', 13)) }
          value.appendChild(el('ins', 'snap-after', line.after))
          list.appendChild(row)
        })
        body.appendChild(list)
      }
      var consequence = el('p', 'snap-consequence' + (c.risk === 'destroy' ? ' snap-consequence--destroy' : ''))
      consequence.appendChild(icon(c.risk === 'destroy' ? 'triangle-alert' : 'undo-2', 14))
      consequence.appendChild(el('span', null, c.consequence))
      if (!(self.voiced && c.risk === 'destroy')) body.appendChild(consequence)   // voiced: the line already says it cannot be undone
      if (c.message) body.appendChild(el('p', 'snap-alert snap-alert--error', c.message))
      var row = el('div', 'snap-actions')
      var yes = el('button', 'snap-btn ' + (c.risk === 'destroy' ? 'snap-btn--danger' : 'snap-btn--primary snap-btn--breathe'))
      yes.type = 'button'
      yes.appendChild(icon(c.risk === 'destroy' ? 'trash-2' : 'check', 16))
      yes.appendChild(el('span', null, c.submit))
      if (c.risk === 'destroy') {
        var id = 'snap-sure-' + Math.random().toString(36).slice(2, 7)
        var sure = el('label', 'snap-sure')
        var box = el('input')
        box.type = 'checkbox'
        box.id = id
        sure.htmlFor = id
        sure.appendChild(box)
        sure.appendChild(el('span', null, 'I understand this cannot be undone'))
        body.appendChild(sure)
        yes.disabled = true
        box.addEventListener('change', function () { yes.disabled = !box.checked })
      }
      yes.addEventListener('click', function () { self.act(c.capability, c.inputs || {}, c.token) })
      var no = el('button', 'snap-btn snap-btn--ghost', 'Cancel')
      no.type = 'button'
      no.addEventListener('click', function () {
        if (self._history && self._history.length) { self.back(); say(self, 'No problem: nothing was changed.') } else self.go(Object.assign(notice('Nothing was changed.'), { tone: 'info', say: 'No problem: nothing was changed.' }))
      })
      row.appendChild(yes)
      row.appendChild(no)
      body.appendChild(row)
    }

    // ---- Show: Answer: one record, a value, a notice ----
    answer (c, body) {
      var self = this
      if (c.done) {
        var done = el('div', 'snap-done')
        done.setAttribute('role', 'status')
        done.appendChild(el('span', 'snap-done-check')).appendChild(icon('check', 18))
        if (!self.voiced) done.appendChild(el('span', null, c.done))
        if (c.undo) {
          var undo = button('snap-link snap-undo', c.undo.label || 'Undo', 'undo-2')
          undo.addEventListener('click', function () { self.act(c.undo.capability, c.undo.input, null, c.heading) })
          done.appendChild(undo)
        }
        body.appendChild(done)
      }
      if (c.variant === 'notice') {
        var n = el('p', 'snap-alert' + (c.tone === 'info' ? ' snap-alert--info' : ' snap-alert--error'))
        n.appendChild(icon(c.tone === 'info' ? 'circle-check' : 'info', 16))
        n.appendChild(el('span', null, c.heading))
        body.appendChild(n)
        return
      }
      if (c.variant === 'fact' && !c.done) { body.appendChild(el('p', 'snap-fact', c.heading)); return }
      if (c.heading && c.variant !== 'fact') body.appendChild(el('p', 'snap-heading', c.heading))
      if ((c.fields || []).length) {
        var details = el('div', 'snap-details')
        c.fields.forEach(function (f) { if (f.value !== '') details.appendChild(self.detail(self.fieldIcon(f.label), f.label, f.value)) })
        body.appendChild(details)
      }
      if ((c.actions || []).length) {
        var row = el('div', 'snap-actions')
        c.actions.forEach(function (a, i) {
          var b = button(i === 0 ? 'snap-btn snap-btn--primary' : (a.risk === 'destroy' ? 'snap-btn snap-btn--ghost snap-btn--ghost-danger' : 'snap-btn snap-btn--ghost'), a.label,
            a.risk === 'destroy' ? 'trash-2' : (a.label === 'Change' ? 'pencil' : 'arrow-right'))
          b.addEventListener('click', function () { self.act(a.capability, a.input, null, c.heading) })
          row.appendChild(b)
        })
        body.appendChild(row)
      }
    }
    fieldIcon (label) {
      var map = { Author: 'feather', Book: 'book-open', Title: 'book-open', 'Published year': 'calendar', Summary: 'text', ISBN: 'barcode', Borrower: 'user', 'Due on': 'calendar-clock', Username: 'mail', Permissions: 'shield-check', Bio: 'text', Name: 'user', Rhythm: 'repeat', Level: 'sparkles', Routine: 'repeat' }
      return map[label] || 'circle-dot'
    }

    // ---- Show: List: rows that open as an Answer, pages ----
    list (c, body) {
      var self = this
      if (!(c.rows || []).length) {
        var empty = el('div', 'snap-empty')
        empty.appendChild(icon('sparkles', 22))
        empty.appendChild(el('p', null, c.empty || 'Nothing here yet.'))
        body.appendChild(empty)
        return
      }
      var list = el('ul', 'snap-rows')
      c.rows.forEach(function (cells, i) {
        var li = el('li', 'snap-rowitem')
        li.style.setProperty('--i', i)
        var openable = c.open && c.keys && c.keys[i] !== null && c.keys[i] !== undefined
        var b = el(openable ? 'button' : 'div', 'snap-rowbtn')
        if (openable) b.type = 'button'
        var text = el('span', 'snap-row-text')
        text.appendChild(el('span', 'snap-row-title', cells[0]))
        var rest = cells.slice(1).filter(function (x) { return x !== '' })
        if (rest.length) text.appendChild(el('span', 'snap-row-meta', rest.join(' · ')))
        b.appendChild(text)
        if (openable) {
          b.appendChild(icon('chevron-right', 16))
          b.addEventListener('click', function () { var inputs = {}; inputs[c.open.input] = c.keys[i]; self.act(c.open.capability, inputs, null, cells[0]) })
        }
        li.appendChild(b)
        list.appendChild(li)
      })
      body.appendChild(list)
      if (c.page && c.page.pages > 1) {
        var pager = el('div', 'snap-pager')
        pager.appendChild(el('span', 'snap-muted', 'Page ' + c.page.page + ' of ' + c.page.pages))
        ;[['Previous', c.page.page - 1, 'chevron-left'], ['Next', c.page.page + 1, 'chevron-right']].forEach(function (p) {
          if (p[1] < 1 || p[1] > c.page.pages) return
          var b = button('snap-icon-btn', p[0], p[2], true)
          b.addEventListener('click', function () { self.act(c.capability, { page: p[1] }) })
          pager.appendChild(b)
        })
        body.appendChild(pager)
      }
    }

    // ---- Track: Task: a progress bar, the steps; refreshes itself, then shows what it ended with ----
    task (c, body) {
      var self = this
      var status = el('p', 'snap-status snap-status--' + c.status)
      status.setAttribute('role', 'status')
      status.appendChild(icon({ queued: 'hourglass', running: 'loader-circle', done: 'circle-check', needs_input: 'message-circle-question', failed: 'triangle-alert' }[c.status] || 'activity', 16))
      var phrase = el('span', 'snap-task-phrase', c.statusLabel)
      status.appendChild(phrase)
      if (c.live) {
        // live: how long it has been running, and what it is doing now (the capability's own words, in turn)
        this._since = this._since || Date.now()
        var timer = el('span', 'snap-task-clock', clock(Math.floor((Date.now() - this._since) / 1000)))
        status.appendChild(timer)
        var words = (c.working && c.working.length ? c.working : ['Working on it…'])
        var n = 0
        clearInterval(this._tick)
        this._tick = setInterval(function () {
          if (!self.isConnected) return clearInterval(self._tick)
          var secs = Math.floor((Date.now() - self._since) / 1000)
          timer.textContent = clock(secs)
          if (c.status === 'running' && secs % 3 === 0) { phrase.textContent = words[n++ % words.length]; phrase.classList.remove('is-swap'); void phrase.offsetWidth; phrase.classList.add('is-swap') }
        }, 1000)
        if (c.status === 'running') phrase.textContent = words[0]
      } else {
        clearInterval(this._tick)
      }
      body.appendChild(status)
      var bar = el('div', 'snap-progress snap-progress--' + c.status)
      bar.appendChild(el('span', 'snap-progress-fill'))
      body.appendChild(bar)
      var steps = el('ol', 'snap-steps')
      ;(c.steps || []).forEach(function (s, i) {
        var li = el('li', s.done ? 'is-done' : (c.live && i === (c.steps || []).filter(function (x) { return x.done }).length ? 'is-now' : ''))
        li.appendChild(icon(s.done ? 'circle-check' : 'circle', 14))
        li.appendChild(el('span', null, s.label + (s.at ? ' · ' + String(s.at).slice(11, 16) : '')))
        steps.appendChild(li)
      })
      body.appendChild(steps)
      if (c.result) {
        var inner = document.createElement('snap-card')
        inner.className = 'snap-inner'
        if (self.voiced) inner.setAttribute('voiced', '')
        inner.card = c.result
        body.appendChild(inner)
      } else if (c.live) {
        if (!self.voiced) body.appendChild(el('p', 'snap-muted', 'You can leave: I will tell you when it is done.'))
      }
      clearTimeout(this._poll)
      if (c.live) {
        this._poll = setTimeout(function () {
          call('snap_task', { id: c.id }).then(function (next) {
            if (!next || !next.snap || !self.isConnected) return
            var finished = !next.live
            self._card = next
            self.render(false)
            if (finished) {
              var done = self.querySelector('.snap')
              if (done && !REDUCED) done.classList.add('is-finished')   // the moment it is ready: a pop and a glow
              self.dispatchEvent(new CustomEvent('snap:next', { bubbles: true, detail: next }))
              say(self, next.say, next)
              if (next.celebrate) self.dispatchEvent(new CustomEvent('snap:celebrate', { bubbles: true, detail: { card: next } }))
            }
          })
        }, 3000)
      }
    }

    // the two quiet ways out of every offer
    dismissals (c, row) {
      var self = this
      ;[['Not now', 'today', 'Okay, I will ask again tomorrow.'], ['Don\'t suggest this again', 'never', 'Got it, I will not suggest this again.']].forEach(function (d) {
        var b = el('button', 'snap-link', d[0])
        b.type = 'button'
        b.addEventListener('click', function () {
          call('snap_dismiss', { fingerprint: c.fingerprint, kind: d[1] }).then(function () {
            say(self, d[2])
            var card = self.querySelector('.snap')
            if (card && !REDUCED) { card.classList.add('is-leaving'); setTimeout(function () { self.remove() }, 200) } else self.remove()
          })
        })
        row.appendChild(b)
      })
    }

    // ---- Offer: Suggestion (an alert too): the moment, the reason, the next step ----
    suggestion (c, body) {
      var self = this
      if (!self.voiced) body.appendChild(el('p', 'snap-question', c.question))
      if (c.items && c.items.length) {
        var list = el('div', 'snap-details')
        c.items.forEach(function (i) { list.appendChild(self.detail('book-open', '', i)) })
        body.appendChild(list)
      }
      if (c.reason) {
        var why = el('p', 'snap-reason')
        why.appendChild(icon(c.variant === 'alert' ? 'info' : 'sparkles', 14))
        why.appendChild(el('span', null, (c.variant === 'alert' ? '' : 'Why: ') + c.reason))
        body.appendChild(why)
      }
      var row = el('div', 'snap-actions')
      var yes = button('snap-btn snap-btn--primary', c.yes || (c.level === 'confirm' ? 'Prepare it' : 'Yes, do it'), c.variant === 'alert' ? 'zap' : 'check')
      yes.addEventListener('click', function () { self.act(c.target.capability, c.target.input) })
      row.appendChild(yes)
      if (c.open) {
        var see = button('snap-btn snap-btn--ghost', 'See them', 'eye')
        see.addEventListener('click', function () { self.act(c.open.capability, c.open.input) })
        row.appendChild(see)
      }
      if (c.fingerprint) this.dismissals(c, row)
      body.appendChild(row)
    }

    // ---- Offer: Routine: do it on its rhythm, at the level you pick (setting it up is confirmed too) ----
    routine (c, body) {
      var self = this
      if (!self.voiced) body.appendChild(el('p', 'snap-question', c.question))
      var why = el('p', 'snap-reason')
      why.appendChild(icon('sparkles', 14))
      why.appendChild(el('span', null, 'Why: ' + c.reason))
      body.appendChild(why)
      var group = el('div', 'snap-levels')
      group.setAttribute('role', 'radiogroup')
      group.setAttribute('aria-label', 'How')
      var name = 'snap-level-' + Math.random().toString(36).slice(2, 7)
      var icons = { suggest: 'bell-ring', confirm: 'list-checks', auto: 'zap' }
      Object.keys(c.levels || {}).forEach(function (level, i) {
        var lab = el('label', 'snap-level')
        var radio = el('input')
        radio.type = 'radio'; radio.name = name; radio.value = level; radio.checked = i === 0
        lab.appendChild(radio)
        lab.appendChild(icon(icons[level] || 'sparkles', 16))
        lab.appendChild(el('span', null, c.levels[level]))
        group.appendChild(lab)
      })
      body.appendChild(group)
      var row = el('div', 'snap-actions')
      var yes = button('snap-btn snap-btn--primary', 'Set up the routine', 'repeat')
      yes.addEventListener('click', function () {
        var picked = group.querySelector('input:checked')
        var inputs = { capability: c.target.capability, inputs: JSON.stringify(c.target.input || {}), hour: c.hour, level: picked ? picked.value : 'suggest' }
        if (c.weekday !== null && c.weekday !== undefined) inputs.weekday = c.weekday
        self.act('snap_routine_save', inputs)
      })
      row.appendChild(yes)
      this.dismissals(c, row)
      body.appendChild(row)
    }

    // ---- Go: Shortcut (and its menu variant) ----
    shortcut (c, body) {
      var self = this
      if (c.variant === 'menu') {
        ;[['show', 'See', 'eye'], ['ask', 'Do', 'zap']].forEach(function (g) {
          var group = (c.items || []).filter(function (i) { return i.family === g[0] })
          if (!group.length) return
          body.appendChild(el('p', 'snap-zone', g[0] === 'show' ? 'See' : 'Do'))
          var chips = el('div', 'snap-chips')
          group.forEach(function (item, i) {
            var chip = button('snap-chip snap-chip--' + item.family, item.title, item.icon || g[2])
            chip.style.setProperty('--i', i)
            chip.addEventListener('click', function () { self.act(item.capability, {}) })
            chips.appendChild(chip)
          })
          body.appendChild(chips)
        })
        return
      }
      if (!self.voiced) body.appendChild(el('p', 'snap-glance', c.glance))
      var row = el('div', 'snap-actions')
      var go = button('snap-btn snap-btn--primary', c.risk === 'read' ? 'Open' : 'Start', 'arrow-right')
      go.addEventListener('click', function () { self.act(c.target.capability, c.target.input) })
      row.appendChild(go)
      var remove = button('snap-icon-btn', 'Remove this shortcut', 'trash-2', true)
      remove.addEventListener('click', function () { call('snap_shortcut_delete', { id: c.id }).then(function () { say(self, 'Removed. You can keep any card as a shortcut again.'); self.remove() }) })
      row.appendChild(remove)
      body.appendChild(row)
    }

    // a card kept as a shortcut says so, and offers to remove it; otherwise it offers to save it (one click, its name)
    shortcutControl (c) {
      var self = this
      var box = el('span', 'snap-keep')
      var draw = function () {
        box.textContent = ''
        if (c.shortcut) {
          var state = el('span', 'snap-keep-state')
          state.appendChild(icon('bookmark-check', 16)); state.appendChild(el('span', null, 'On your Home'))
          box.appendChild(state)
          var remove = button('snap-btn snap-btn--quiet snap-btn--sm', 'Remove', 'x')
          remove.setAttribute('aria-label', 'Remove this shortcut from your Home')
          remove.addEventListener('click', function () {
            call('snap_shortcut_delete', { id: c.shortcut }).then(function (gone) {
              if (!gone) return say(self, 'I could not remove it. Try again in a moment.')
              c.shortcut = null; draw()
              say(self, 'Removed from your Home.')
              self.dispatchEvent(new CustomEvent('snap:shortcut', { bubbles: true }))
            })
          })
          box.appendChild(remove)
        } else {
          var save = button('snap-btn snap-btn--quiet snap-btn--sm', 'Save to Home', 'bookmark-plus')
          save.addEventListener('click', function () {
            var name = c.variant === 'record' && c.heading ? c.heading : c.title
            call('snap_shortcut_save', { title: name, capability: c.capability, inputs: c.input || {} }).then(function (saved) {
              if (!saved || !saved.snap) return say(self, 'I could not save it: you may have too many shortcuts already.')
              c.shortcut = saved.id; draw()
              say(self, 'Saved! "' + saved.title + '" is one click away on your Home.')
              self.dispatchEvent(new CustomEvent('snap:shortcut', { bubbles: true, detail: saved }))
            })
          })
          box.appendChild(save)
        }
      }
      draw()
      return box
    }

    // ---- the foot: back, keep as a shortcut, the card's address, the page in full ----
    foot (c) {
      var self = this
      var foot = el('footer', 'snap-foot')
      if (this._history && this._history.length) {
        var back = button('snap-icon-btn', 'Back', 'chevron-left', true)
        back.addEventListener('click', function () { self.back() })
        foot.appendChild(back)
      }
      if (c.family === 'show' && c.variant !== 'notice' && meta('snap-signed-in') === 'yes' && !/^snap_/.test(c.capability || '')) {
        foot.appendChild(this.shortcutControl(c))
      }
      if (c.url && !this.voiced) {   // the task space has its own address
        var link = el('a', 'snap-icon-btn')
        link.href = address(c.url)
        link.setAttribute('aria-label', 'Link to this card')
        link.title = 'Link to this card'
        link.appendChild(icon('link', 16))
        foot.appendChild(link)
      }
      if (c.full) {
        var full = el('a', 'snap-link snap-link--full')
        full.href = address(c.full)
        full.appendChild(el('span', null, 'Open in full'))
        full.appendChild(icon('arrow-up-right', 14))
        foot.appendChild(full)
      }
      return foot
    }
  }

  if (!customElements.get('snap-card')) customElements.define('snap-card', SnapCard)
  window.Snap = { run: run, ask: ask, call: call, notice: notice, icon: icon, avatar: avatar }
})()
