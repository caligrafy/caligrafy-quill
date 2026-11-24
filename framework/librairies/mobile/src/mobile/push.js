/*
 * push.js - Notifications on this phone (Caligrafy\Notify, 2.27.0+): one Notify::user() on the server reaches the
 * person's browsers, emails and phones
 *
 * iPhone and iPad: Apple's push service (APNS_* settings of the site, and the Push Notifications capability of the
 * Xcode project). Android: Firebase (FCM_SERVICE_ACCOUNT of the site, and google-services.json in android/app: without
 * it, registering would stop the app, so push stays off there).
 */

import { Capacitor } from '@capacitor/core'
import { Device } from '@capacitor/device'
import { PushNotifications } from '@capacitor/push-notifications'
import { call } from './session.js'
import features from './features.json'

const platform = Capacitor.getPlatform()

// Whether this build can receive notifications at all
export function pushAvailable () {
  return Capacitor.isNativePlatform() && (platform === 'ios' || features.androidPush === true)
}

// Asks the person, registers this phone with Apple or Firebase, then with the site (notify_register_device).
// Resolves with Notify's status; rejects with an Error that says why it could not
export async function enablePush () {
  if (!pushAvailable()) {
    throw new Error(Capacitor.isNativePlatform() ? 'Notifications are not set up in this build (google-services.json)' : 'Notifications work in the phone app')
  }
  // the site first: without Apple's or Firebase's settings it cannot send any, so the person is not asked for nothing
  const provider = platform === 'ios' ? 'apns' : 'fcm'
  const status = await call('notify_status')
  if (!status.push || !status.push[provider]) {
    throw new Error('This app does not send notifications to ' + (platform === 'ios' ? 'iPhones' : 'Android phones') + ' yet')
  }
  let permission = await PushNotifications.checkPermissions()
  if (permission.receive === 'prompt' || permission.receive === 'prompt-with-rationale') {
    permission = await PushNotifications.requestPermissions()
  }
  if (permission.receive !== 'granted') throw new Error('Notifications are off for this app in the phone\'s settings')
  const token = await new Promise((resolve, reject) => {
    const timer = setTimeout(() => reject(new Error((platform === 'ios' ? 'Apple' : 'Google') + ' did not answer: check the connection and try again')), 20000)
    PushNotifications.addListener('registration', ({ value }) => { clearTimeout(timer); resolve(value) })
    PushNotifications.addListener('registrationError', ({ error }) => { clearTimeout(timer); reject(new Error(error || 'Registration failed')) })
    PushNotifications.register().catch(reject)
  })
  const name = await Device.getInfo().then(info => info.name || info.model || '').catch(() => '')
  return call('notify_register_device', { provider, token, name: name.slice(0, 100) })
}

// Turns them off on this phone (the site forgets it; signing out does it too)
export async function disablePush (token) {
  await PushNotifications.unregister().catch(() => {})
  if (token) await call('notify_forget_device', { token }).catch(() => {})
}

// Called with { title, body, url } when the person taps a notification
export function onNotificationOpened (listener) {
  if (!Capacitor.isNativePlatform()) return () => {}
  const handle = PushNotifications.addListener('pushNotificationActionPerformed', ({ notification }) => {
    const data = notification.data || {}
    listener({ title: notification.title, body: notification.body, url: data.url || '' })
  })
  return () => handle.then(h => h.remove())
}
