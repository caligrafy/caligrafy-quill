// native.mjs - Gets the native projects ready (npm run native; run by npm run sync, ios and android). Safe to run
// again: each change is made only when it is missing.
//   - the app's URL scheme (its appId), through which the site's sign-in page sends the person back to the app:
//     CFBundleURLTypes in ios/App/App/Info.plist, an intent filter in android/app/src/main/AndroidManifest.xml
//   - iOS push: the aps-environment entitlement and the two AppDelegate methods @capacitor/push-notifications needs
//   - Android push: on only when android/app/google-services.json is there (src/mobile/features.json)
//   - Android versions: android/app/build.gradle reads the ones fastlane gives
//   - the icon and splash pictures (assets/), from src/assets/caligrafy-mark.svg the first time: npm run icons
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const at = path => join(root, path)
const config = JSON.parse(readFileSync(at('capacitor.config.json'), 'utf8'))
const scheme = config.appId.toLowerCase()
const done = []

function edit (path, change) {
  if (!existsSync(at(path))) return
  const before = readFileSync(at(path), 'utf8')
  const after = change(before)
  if (after !== before) {
    writeFileSync(at(path), after)
    done.push(path)
  }
}

// iOS -----------------------------------------------------------------------------------------------------------------
edit('ios/App/App/Info.plist', plist => {
  if (plist.includes(`<string>${scheme}</string>`)) return plist
  const types = `\t<key>CFBundleURLTypes</key>\n\t<array>\n\t\t<dict>\n\t\t\t<key>CFBundleURLName</key>\n\t\t\t<string>${scheme}</string>\n`
    + `\t\t\t<key>CFBundleURLSchemes</key>\n\t\t\t<array>\n\t\t\t\t<string>${scheme}</string>\n\t\t\t</array>\n\t\t</dict>\n\t</array>\n`
  const end = plist.lastIndexOf('</dict>')
  return plist.slice(0, end) + types + plist.slice(end)
})

edit('ios/App/App/AppDelegate.swift', swift => {
  if (swift.includes('capacitorDidRegisterForRemoteNotifications')) return swift
  const methods = `
    // push notifications (@capacitor/push-notifications): the device token Apple gives, or why it could not
    func application(_ application: UIApplication, didRegisterForRemoteNotificationsWithDeviceToken deviceToken: Data) {
        NotificationCenter.default.post(name: .capacitorDidRegisterForRemoteNotifications, object: deviceToken)
    }

    func application(_ application: UIApplication, didFailToRegisterForRemoteNotificationsWithError error: Error) {
        NotificationCenter.default.post(name: .capacitorDidFailToRegisterForRemoteNotifications, object: error)
    }
`
  const end = swift.lastIndexOf('}')
  return swift.slice(0, end) + methods + swift.slice(end)
})

if (existsSync(at('ios/App/App')) && !existsSync(at('ios/App/App/App.entitlements'))) {
  // development here; the App Store export writes production
  writeFileSync(at('ios/App/App/App.entitlements'), '<?xml version="1.0" encoding="UTF-8"?>\n'
    + '<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">\n'
    + '<plist version="1.0">\n<dict>\n\t<key>aps-environment</key>\n\t<string>development</string>\n</dict>\n</plist>\n')
  done.push('ios/App/App/App.entitlements')
}

edit('ios/App/App.xcodeproj/project.pbxproj', project => {
  if (project.includes('CODE_SIGN_ENTITLEMENTS')) return project
  // the build settings of the App target (Debug and Release) are the ones that name its Info.plist
  return project.replace(/(\n(\s*)INFOPLIST_FILE = App\/Info\.plist;)/g, '\n$2CODE_SIGN_ENTITLEMENTS = App/App.entitlements;$1')
})

// Android -------------------------------------------------------------------------------------------------------------
edit('android/app/src/main/AndroidManifest.xml', manifest => {
  if (manifest.includes(`android:scheme="${scheme}"`)) return manifest
  const filter = `\n            <intent-filter>\n                <action android:name="android.intent.action.VIEW" />\n`
    + `                <category android:name="android.intent.category.DEFAULT" />\n                <category android:name="android.intent.category.BROWSABLE" />\n`
    + `                <data android:scheme="${scheme}" android:host="auth" />\n            </intent-filter>\n`
  const end = manifest.indexOf('</intent-filter>')
  return end < 0 ? manifest : manifest.slice(0, end + '</intent-filter>'.length) + filter + manifest.slice(end + '</intent-filter>'.length)
})

// the version and build number the store builds give (fastlane: -PversionCode, -PversionName), 1 and 1.0 otherwise
edit('android/app/build.gradle', gradle => gradle
  .replace(/versionCode \d+\s*$/m, "versionCode((project.findProperty('versionCode') ?: '1') as Integer)")
  .replace(/versionName "[^"]*"\s*$/m, "versionName(project.findProperty('versionName') ?: '1.0')"))

const androidPush = existsSync(at('android/app/google-services.json'))
edit('src/mobile/features.json', () => JSON.stringify({ androidPush }, null, 2) + '\n')

// Icon and splash pictures (npm run icons makes every size from them) ----------------------------------------------------
if (!existsSync(at('assets/icon-only.png'))) {
  try {
    const { default: sharp } = await import('sharp')
    mkdirSync(at('assets'), { recursive: true })
    const mark = readFileSync(at('src/assets/caligrafy-mark.svg'))
    const picture = async (file, size, share) => {
      const inner = Math.round(size * share)
      const layers = inner > 0
        ? [{ input: await sharp(mark, { density: 600 }).resize(inner, inner, { fit: 'contain', background: '#ffffff00' }).png().toBuffer(), gravity: 'center' }]
        : []
      await sharp({ create: { width: size, height: size, channels: 4, background: '#ffffff' } }).composite(layers).png().toFile(at(file))
      done.push(file)
    }
    await picture('assets/icon-only.png', 1024, 0.6)
    await picture('assets/icon-foreground.png', 1024, 0.45)
    await picture('assets/icon-background.png', 1024, 0)
    await picture('assets/splash.png', 2732, 0.18)
    await picture('assets/splash-dark.png', 2732, 0.18)
  } catch (error) {
    console.log(' Icons: put a 1024x1024 assets/icon-only.png and a 2732x2732 assets/splash.png, then npm run icons (' + error.message + ')')
  }
}

console.log(done.length ? ' Ready: ' + done.join(', ') : ' Native projects already set up')
if (existsSync(at('android')) && !androidPush) {
  console.log(' Android push is off until android/app/google-services.json is there (Firebase console, Project settings, Your apps)')
}
