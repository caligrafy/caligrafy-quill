/*
 * A page shown inside a Snap card (?snap-embed=<capability>, 2.24.0): without the site's frame; only the part of the
 * view marked data-snap="<capability>" when there is one; links open in the page around the card; the card learns the
 * page's height as it changes. Loaded by the framework (Snap\Views::page), never by a view.
 */
(function () {
  'use strict'
  if (window.parent === window) return
  document.documentElement.classList.add('snap-embedded')
  var name = new URLSearchParams(window.location.search).get('snap-embed') || ''
  var reads = !!(document.currentScript && document.currentScript.getAttribute('data-reads'))

  // links leave the card: they open in the assistant's page
  var base = document.createElement('base')
  base.target = '_top'
  document.head.appendChild(base)

  function focusPart () {
    if (!name) return
    var parts = document.querySelectorAll('[data-snap]')
    var part = null
    parts.forEach(function (p) { if (!part && (' ' + p.getAttribute('data-snap') + ' ').indexOf(' ' + name + ' ') >= 0) part = p })
    if (!part) { if (reads) readingPart(); return }
    // the part alone: everything around it, at every level, steps aside
    part.classList.add('snap-embed-part')
    for (var node = part; node && node !== document.body; node = node.parentElement) {
      Array.prototype.forEach.call(node.parentElement ? node.parentElement.children : [], function (sibling) {
        if (sibling !== node && sibling.tagName !== 'SCRIPT' && sibling.tagName !== 'STYLE' && sibling.tagName !== 'LINK') sibling.classList.add('snap-embed-hidden')
      })
    }
  }

  // no mark, and the card only reads: the forms that change things step aside, and what held only them
  function readingPart () {
    document.querySelectorAll('form').forEach(function (form) {
      if ((form.getAttribute('method') || 'get').toLowerCase() !== 'get') form.classList.add('snap-embed-hidden')
    })
    // a band, a box or a note left with nothing to show goes too (errors and notes of the hidden forms)
    document.querySelectorAll('.cq-field-error, [role=alert]').forEach(function (el) { el.classList.add('snap-embed-hidden') })
    var blocks = Array.prototype.slice.call(document.querySelectorAll('body section, body article, body div, body p')).reverse()
    blocks.forEach(function (el) {
      if (el.classList.contains('snap-embed-hidden') || el.querySelector('img, svg, canvas, video, iframe')) return
      var shown = Array.prototype.some.call(el.children, function (c) { return !c.classList.contains('snap-embed-hidden') && c.tagName !== 'SCRIPT' && c.tagName !== 'STYLE' })
      var text = Array.prototype.some.call(el.childNodes, function (n) { return n.nodeType === 3 && n.textContent.trim() !== '' })
      if (el.children.length && !shown && !text) el.classList.add('snap-embed-hidden')
    })
  }

  // the site's frame, whatever the layout: the bars, menus and side rails around the page's main content
  function unframe () {
    var main = document.querySelector('main, [role=main]')
    if (!main) return
    document.querySelectorAll('header, nav, aside, footer, [role=banner], [role=navigation], [role=complementary], [role=contentinfo]').forEach(function (el) {
      if (!main.contains(el) && !el.contains(main)) el.classList.add('snap-embed-hidden')
    })
  }

  // a layout that scrolls inside the screen (a full-height shell, panels that scroll on their own) unrolls: the page
  // takes its whole height, so the card knows how tall it is
  function unroll () {
    var nodes = [document.documentElement, document.body].concat(Array.prototype.slice.call(document.body.querySelectorAll('*')))
    nodes.forEach(function (el) {
      if (el.classList.contains('snap-embed-hidden') || el.closest('.snap-embed-hidden')) return
      var style = getComputedStyle(el)
      if (style.position === 'fixed') return
      var scrolls = /(auto|scroll|hidden)/.test(style.overflowY) && el.scrollHeight > el.clientHeight + 2
      if (scrolls || /(auto|scroll)/.test(style.overflowY)) {
        el.style.setProperty('overflow-y', 'visible', 'important')
        el.style.setProperty('height', 'auto', 'important')
        el.style.setProperty('max-height', 'none', 'important')
        el.style.setProperty('min-height', '0', 'important')
      }
    })
    // the shells around the content stop filling the screen
    var main = document.querySelector('main, [role=main]')
    for (var node = main; node && node !== document.documentElement; node = node.parentElement) {
      node.style.setProperty('height', 'auto', 'important')
      node.style.setProperty('min-height', '0', 'important')
      node.style.setProperty('overflow', 'visible', 'important')
    }
  }

  var last = 0
  function report () {
    var height = Math.ceil(Math.max(document.body.scrollHeight, document.body.offsetHeight))
    if (Math.abs(height - last) < 2) return
    last = height
    window.parent.postMessage({ snapEmbed: name, height: height, href: window.location.pathname }, window.location.origin)
  }

  function ready () {
    unframe()
    focusPart()
    unroll()
    report()
    if ('ResizeObserver' in window) new ResizeObserver(report).observe(document.body)
    setTimeout(report, 300)
    setTimeout(report, 1200)
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready)
  else ready()
  window.addEventListener('load', report)
})()
