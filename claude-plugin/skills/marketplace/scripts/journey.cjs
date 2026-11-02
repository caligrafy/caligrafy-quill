// journey.cjs: walks an app the way a person would and takes the screenshots of its key features.
// node journey.cjs <steps.json> <out folder>
// steps.json: {"base": "http://localhost:8000", "camera": "camera.jpg", "steps": [
//   {"goto": "/"}, {"fill": "#email", "value": "demo@example.test"}, {"click": "text=Sign in"}, {"press": "Enter"},
//   {"wait": 1500} or {"wait": "#dashboard"}, {"shot": "dashboard", "caption": "Your dashboard"}]}
// camera (optional): a picture (JPEG, PNG, WebP) or a short clip (MP4, WebM) next to steps.json, played as the camera of
// the page (getUserMedia), so a camera app shows a realistic scene; without it, Chromium's test pattern.
// Prints one line per screenshot and SHOTS <file> at the end: [{"file", "caption"}], 5 at most, all different.
const fs = require('fs')
const path = require('path')
const crypto = require('crypto')
const { chromium } = require('playwright')

const MAX = 5
const [stepsFile, out] = process.argv.slice(2)
const plan = JSON.parse(fs.readFileSync(stepsFile, 'utf8'))
fs.mkdirSync(out, { recursive: true })

;(async () => {
  // a fake camera and microphone, allowed without a prompt: camera apps show their screens
  const browser = await chromium.launch({ args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream'] })
  const page = await browser.newPage({ viewport: { width: 1280, height: 800 }, deviceScaleFactor: 1 })
  if (plan.camera) await page.addInitScript(fakeCamera, cameraSource(path.resolve(path.dirname(stepsFile), plan.camera)))
  page.setDefaultTimeout(15000)
  const shots = []
  const seen = new Set()
  let current = null
  try {
    for (const step of plan.steps) {
      current = step
      if (step.goto) await page.goto(new URL(step.goto, plan.base).href, { waitUntil: 'networkidle' })
      else if (step.fill) await page.fill(step.fill, String(step.value ?? ''))
      else if (step.click) await page.click(step.click)
      else if (step.press) await page.keyboard.press(step.press)
      else if (typeof step.wait === 'number') await page.waitForTimeout(step.wait)
      else if (step.wait) await page.waitForSelector(step.wait)
      else if (step.shot) {
        if (shots.length >= MAX) { console.log(`skip ${step.shot}: ${MAX} screenshots already`); continue }
        await page.waitForLoadState('networkidle').catch(() => {})
        const png = await page.screenshot({ type: 'png' })
        const hash = crypto.createHash('sha1').update(png).digest('hex')
        if (seen.has(hash)) { console.log(`skip ${step.shot}: the same screen as another one`); continue }
        seen.add(hash)
        const file = `${String(shots.length + 1).padStart(2, '0')}-${step.shot.replace(/[^a-z0-9-]+/gi, '-')}.png`
        fs.writeFileSync(path.join(out, file), png)
        shots.push({ file, caption: step.caption || step.shot })
        console.log(`shot ${file} (${Math.round(png.length / 1024)} KB): ${step.caption || ''}`)
      }
    }
  } catch (e) {
    console.log(`FAIL at ${JSON.stringify(current)}: ${e.message.split('\n')[0]} (the page then: failed.png)`)
    await page.screenshot({ path: path.join(out, 'failed.png') }).catch(() => {})
    process.exitCode = 1
  } finally {
    fs.writeFileSync(path.join(out, 'shots.json'), JSON.stringify(shots, null, 2))
    console.log(`SHOTS ${path.join(out, 'shots.json')} ${shots.length}`)
    await browser.close()
  }
})()

// the camera file as a data URL, with its kind
function cameraSource (file) {
  const types = { '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.png': 'image/png', '.webp': 'image/webp', '.mp4': 'video/mp4', '.webm': 'video/webm' }
  const type = types[path.extname(file).toLowerCase()]
  if (!type) throw new Error(`camera: a JPEG, PNG, WebP, MP4 or WebM file, not ${file}`)
  return { url: `data:${type};base64,${fs.readFileSync(file).toString('base64')}`, video: type.startsWith('video/') }
}

// runs in the page before its own scripts: getUserMedia returns a 1280x720 stream of the picture or clip (cover fit)
function fakeCamera (source) {
  const W = 1280; const H = 720
  const start = () => {
    const canvas = document.createElement('canvas')
    canvas.width = W; canvas.height = H
    const ctx = canvas.getContext('2d')
    const media = document.createElement(source.video ? 'video' : 'img')
    if (source.video) Object.assign(media, { muted: true, loop: true, playsInline: true, autoplay: true })
    media.src = source.url
    if (source.video) media.play().catch(() => {})
    const draw = () => {
      const w = media.videoWidth || media.naturalWidth; const h = media.videoHeight || media.naturalHeight
      if (w && h) {
        const scale = Math.max(W / w, H / h)
        ctx.drawImage(media, (W - w * scale) / 2, (H - h * scale) / 2, w * scale, h * scale)
      }
      requestAnimationFrame(draw)
    }
    draw()
    return canvas.captureStream(30)
  }
  let stream = null
  const devices = navigator.mediaDevices
  if (!devices) return
  devices.getUserMedia = async (constraints) => {
    if (!constraints || !constraints.video) throw new DOMException('No microphone here', 'NotFoundError')
    stream = stream || start()
    return new MediaStream(stream.getVideoTracks().map((t) => t.clone()))
  }
  devices.enumerateDevices = async () => [{ deviceId: 'caligrafy-camera', groupId: 'caligrafy', kind: 'videoinput', label: 'Camera', toJSON () { return this } }]
}
