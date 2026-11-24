/*
 * session.js - Who is signed in to this app, and the calls to the Caligrafy application as that person
 *
 * Signing in (Route::mobile in web.php, Caligrafy 2.27.0+): the app opens the site's own sign-in page in the system
 * browser (/mobile/authorize, with a PKCE challenge), the person signs in and allows this device, the site sends
 * them back to the app (<app id>://auth?code=...) and the app trades the code and its verifier for a personal access
 * token of that person (/mobile/token). The token is kept on the device (Preferences) and sent as
 * Authorization: Bearer to the capabilities (Route::capabilities): no cookie, no CSRF token, no password in the app.
 * In a browser (npm run dev), the site sends the code back to this page instead, only while it runs on this computer.
 */

import { Capacitor } from '@capacitor/core'
import { App } from '@capacitor/app'
import { Browser } from '@capacitor/browser'
import { Device } from '@capacitor/device'
import { Preferences } from '@capacitor/preferences'
import capacitor from '../../capacitor.config.json'

const KEY = 'caligrafy.session'
const PENDING = 'caligrafy.signin'
const native = Capacitor.isNativePlatform()

export const apiRoute = (import.meta.env.VITE_API_ROUTE || '').replace(/\/+$/, '')
const capabilitiesRoute = import.meta.env.VITE_CAPABILITIES_ROUTE || '/capabilities'
const mobileRoute = import.meta.env.VITE_MOBILE_ROUTE || '/mobile'
const scheme = capacitor.appId.toLowerCase()

let session = null          // { token, expires_at, user: { id, username, permissions } }
let waiting = null          // the sign-in under way: { resolve, reject }
const listeners = new Set()

// ---- what the app reads ------------------------------------------------------------------------------------------

export function currentUser () {
  return session ? session.user : null
}

export function signedIn () {
  return session !== null
}

// called with the user (or null) each time someone signs in or out
export function onSessionChange (listener) {
  listeners.add(listener)
  return () => listeners.delete(listener)
}

function changed () {
  listeners.forEach(listener => listener(currentUser()))
}

async function save (value) {
  session = value
  if (value) await Preferences.set({ key: KEY, value: JSON.stringify(value) })
  else await Preferences.remove({ key: KEY })
  changed()
}

// The session kept on this device, still valid: called once when the app starts. Offline, the kept session is used;
// a token the site no longer accepts (revoked, expired, account deleted) signs the app out
export async function restore () {
  if (!native) await finishInBrowser()
  const kept = await Preferences.get({ key: KEY })
  let value = null
  try { value = kept.value ? JSON.parse(kept.value) : null } catch { value = null }
  if (value && value.expires_at && new Date(value.expires_at.replace(' ', 'T')) <= new Date()) value = null
  session = value
  if (session) {
    try {
      const answer = await request('GET', mobileRoute + '/session')
      session.user = answer.user
      await save(session)
    } catch (error) {
      if (error.status === 401) await save(null)
    }
  }
  changed()
  return currentUser()
}

// ---- signing in and out ------------------------------------------------------------------------------------------

function base64url (bytes) {
  return btoa(String.fromCharCode(...new Uint8Array(bytes))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')
}

function random (size) {
  return base64url(crypto.getRandomValues(new Uint8Array(size)))
}

async function deviceName () {
  try {
    const info = await Device.getInfo()
    return (info.name || info.model || 'a phone').slice(0, 60)
  } catch {
    return 'a phone'
  }
}

// Opens the site's sign-in page; resolves with the user once they allowed this device (rejects when they did not)
export async function signIn () {
  const verifier = random(32)
  const challenge = base64url(await crypto.subtle.digest('SHA-256', new TextEncoder().encode(verifier)))
  const state = random(16)
  await Preferences.set({ key: PENDING, value: JSON.stringify({ verifier, state }) })
  const query = new URLSearchParams({ challenge, method: 'S256', state, device: native ? await deviceName() : 'this browser' })
  if (native) query.set('scheme', scheme)
  else query.set('redirect', window.location.origin)
  const url = apiRoute + mobileRoute + '/authorize?' + query.toString()
  if (!native) {
    window.location.assign(url)       // the page comes back with ?mobile_auth=1&code=... (finishInBrowser)
    return new Promise(() => {})
  }
  return new Promise((resolve, reject) => {
    waiting = { resolve, reject }
    Browser.open({ url, presentationStyle: 'popover' }).catch(reject)
  })
}

// <app id>://auth?state=...&code=... (or &error=access_denied): the site sending the person back to the app
async function finish (params) {
  const kept = await Preferences.get({ key: PENDING })
  await Preferences.remove({ key: PENDING })
  let pending = null
  try { pending = kept.value ? JSON.parse(kept.value) : null } catch { pending = null }
  if (!pending || params.get('state') !== pending.state) throw new Error('This sign-in is not the one this app started: try again')
  if (params.get('error')) throw new Error(params.get('error') === 'access_denied' ? 'Sign-in cancelled' : params.get('error'))
  const answer = await request('POST', mobileRoute + '/token', { code: params.get('code'), verifier: pending.verifier }, false)
  await save({ token: answer.token, expires_at: answer.expires_at, user: answer.user })
  return answer.user
}

if (native) {
  App.addListener('appUrlOpen', async ({ url }) => {
    if (!url.toLowerCase().startsWith(scheme + '://auth')) return
    try { await Browser.close() } catch { /* Android closes its tab itself */ }
    const done = waiting
    waiting = null
    try {
      const user = await finish(new URL(url).searchParams)
      if (done) done.resolve(user)
    } catch (error) {
      if (done) done.reject(error)
    }
  })
  // the browser closed without an answer (the person tapped Done): the sign-in is cancelled
  Browser.addListener('browserFinished', () => {
    setTimeout(() => {
      if (waiting) { waiting.reject(new Error('Sign-in cancelled')); waiting = null }
    }, 800)
  })
}

async function finishInBrowser () {
  const params = new URLSearchParams(window.location.search)
  if (!params.has('mobile_auth')) return
  window.history.replaceState(null, '', window.location.pathname + window.location.hash)
  try { await finish(params) } catch (error) { console.warn(error.message) }
}

// Revokes this app's token on the site (and its push registration), then forgets it here: signed out even offline
export async function signOut () {
  if (session) {
    try { await request('POST', mobileRoute + '/signout') } catch { /* signed out here anyway */ }
  }
  await save(null)
}

// ---- calls ----------------------------------------------------------------------------------------------------------

// One capability, as the signed-in person: await call('list_books', { page: 2 }). Rejects with an Error whose
// status, code and fields are the answer's ({ error: { code, message, fields } } of Caligrafy\ServiceError)
export function call (name, input = {}) {
  return request('POST', capabilitiesRoute + '/' + encodeURIComponent(name), input)
}

async function request (method, path, body, withToken = true) {
  const headers = { Accept: 'application/json' }
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  if (withToken && session) headers.Authorization = 'Bearer ' + session.token
  let response
  try {
    response = await fetch(apiRoute + path, { method, headers, body: body === undefined ? undefined : JSON.stringify(body), credentials: 'omit' })
  } catch {
    throw Object.assign(new Error('No connection to ' + (apiRoute || 'the app') + ': check the network and try again'), { status: 0, code: 'offline' })
  }
  const answer = await response.json().catch(() => ({}))
  if (!response.ok || answer.error) {
    const error = answer.error || {}
    if (response.status === 401 && withToken && session && path !== mobileRoute + '/signout') await save(null)
    throw Object.assign(new Error(error.message || 'The app answered ' + response.status), { status: response.status, code: error.code, fields: error.fields || {} })
  }
  return answer.data
}
