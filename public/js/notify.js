// notify.js: turn notifications on in a page of a Caligrafy app (2.15.0, Caligrafy\Notify)
// <script src="{$home}public/js/notify.js"></script>, with Route::capabilities() and Route::notifications() in web.php.
// Notify.status()   unsupported, install-needed (iPhone and iPad outside the Home Screen app), denied, off or on
// Notify.enable()   asks permission (call it from a click), subscribes this browser, saves it; returns the new status
// Notify.disable()  unsubscribes this browser
// Notify.test()     sends a test message to the signed-in user
// Notify.email(address), Notify.channel('email', false): the email channel; Notify.info(): what notify_status says
(function () {
  var script = document.currentScript && document.currentScript.src
  var base = script ? script.replace(/public\/js\/notify\.js(\?.*)?$/, '') : '/'
  var csrf = null

  function request (method, path, body) {
    var headers = { Accept: 'application/json' }
    var ready = method === 'GET' ? Promise.resolve() : token().then(function (t) { headers['X-CSRF-Token'] = t })
    return ready.then(function () {
      if (body !== undefined) headers['Content-Type'] = 'application/json'
      return fetch(base + 'capabilities' + path, { method: method, headers: headers, credentials: 'same-origin', body: body === undefined ? undefined : JSON.stringify(body) })
    }).then(function (response) {
      return response.json().catch(function () { return null }).then(function (json) {
        if (!response.ok) { var e = new Error((json && json.error && json.error.message) || 'Request failed'); e.status = response.status; throw e }
        return json
      })
    })
  }
  function token () {
    if (csrf !== null) return Promise.resolve(csrf)
    return request('GET', '').then(function (json) { csrf = json.csrf || ''; return csrf })
  }
  function call (name, input) { return request('POST', '/' + name, input || {}).then(function (json) { return json.data }) }

  function supported () { return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window }
  function iosOutsideApp () {
    var ios = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
    var standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true
    return ios && !standalone
  }
  function worker () {
    return navigator.serviceWorker.getRegistration(base).then(function (registration) {
      return registration || navigator.serviceWorker.register(base + 'public/sw.js', { scope: base })
    }).then(function () { return navigator.serviceWorker.ready })
  }
  function key (base64) {
    var padded = (base64 + '===='.slice(base64.length % 4)).replace(/-/g, '+').replace(/_/g, '/')
    var raw = atob(padded), out = new Uint8Array(raw.length)
    for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i)
    return out
  }

  function status () {
    if (iosOutsideApp() && !('PushManager' in window)) return Promise.resolve('install-needed')
    if (!supported()) return Promise.resolve('unsupported')
    if (Notification.permission === 'denied') return Promise.resolve('denied')
    return navigator.serviceWorker.getRegistration(base).then(function (registration) {
      return registration ? registration.pushManager.getSubscription() : null
    }).then(function (subscription) { return subscription && Notification.permission === 'granted' ? 'on' : 'off' })
  }

  function enable () {
    return status().then(function (now) {
      if (now === 'unsupported' || now === 'install-needed' || now === 'denied') return now
      return Notification.requestPermission().then(function (permission) {
        if (permission !== 'granted') return permission === 'denied' ? 'denied' : 'off'
        return Promise.all([worker(), call('notify_status')]).then(function (both) {
          var info = both[1]
          if (!info.push.configured) throw new Error('Push notifications are not set up on this app (php caligrafer.php notify keys)')
          return both[0].pushManager.getSubscription().then(function (existing) {
            return existing || both[0].pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key(info.push.public_key) })
          })
        }).then(function (subscription) {
          return call('notify_subscribe', subscription.toJSON()).then(function () { return 'on' })
        })
      })
    })
  }

  function disable () {
    if (!supported()) return Promise.resolve('unsupported')
    return navigator.serviceWorker.getRegistration(base).then(function (registration) {
      return registration ? registration.pushManager.getSubscription() : null
    }).then(function (subscription) {
      if (!subscription) return 'off'
      return call('notify_unsubscribe', { endpoint: subscription.endpoint }).then(function () { return subscription.unsubscribe() }).then(function () { return 'off' })
    })
  }

  window.Notify = {
    status: status,
    enable: enable,
    disable: disable,
    test: function () { return call('notify_test') },
    info: function () { return call('notify_status') },
    email: function (address) { return call('notify_email_set', { email: address }) },
    channel: function (channel, enabled) { return call('notify_preferences', { channel: channel, enabled: enabled }) }
  }
})()
