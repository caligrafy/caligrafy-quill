// Pure liveness logic of FaceDetector (public/js/services/detect.js) and the passkey encoding helpers
// (public/js/services/passkey.js), without a browser or a camera. Run: node qa/tests/liveness_tests.js
const assert = require('assert');
const path = require('path');
const { FaceDetector, LivenessTracker } = require(path.join(__dirname, '../../public/js/services/detect.js'));
const { PasskeyClient } = require(path.join(__dirname, '../../public/js/services/passkey.js'));

let pass = 0, fail = 0;
function test(name, fn) {
    try { fn(); pass++; console.log('  PASS ' + name); }
    catch (e) { fail++; console.log('  FAIL ' + name + '\n       ' + e.message); }
}

// 68 landmarks of a face with the nose shifted by `yaw` (-1..1)
function landmarks(yaw = 0) {
    const p = Array.from({ length: 68 }, () => ({ x: 50, y: 50 }));
    p[0] = { x: 0, y: 40 }; p[16] = { x: 100, y: 40 };
    p[30] = { x: 50 + yaw * 50, y: 55 };
    return p;
}
// frames are [yaw, happy]
const frame = (yaw, happy, at) => FaceDetector.frameSummary(landmarks(yaw), { happy: happy }, at);
const feed = (tracker, frames) => frames.forEach((f, i) => tracker.add(frame(f[0], f[1], i * 100)));

test('head yaw: 0 frontal, > 0 to the person\'s left (nose towards jaw point 16), < 0 to the right', () => {
    assert.strictEqual(FaceDetector.headYaw(landmarks(0)), 0);
    assert.ok(Math.abs(FaceDetector.headYaw(landmarks(0.5)) - 0.5) < 1e-9);
    assert.ok(FaceDetector.headYaw(landmarks(-0.4)) < -0.39);
});

test('the challenges are turn-left, turn-right and smile (blink was removed)', () => {
    assert.deepStrictEqual(FaceDetector.LIVENESS_CHALLENGES, ['turn-left', 'turn-right', 'smile']);
    assert.throws(() => new LivenessTracker(['blink']), /Unknown liveness challenge: blink/);
    assert.deepStrictEqual(Object.keys(frame(0, 0, 0)).sort(), ['at', 'happy', 'yaw']);
});

test('turn-left / turn-right: start facing the camera, then turn the right way', () => {
    const left = new LivenessTracker(['turn-left']);
    feed(left, [[0.05, 0], [-0.5, 0]]);
    assert.ok(!left.done(), 'a turn to the right is not a turn to the left');
    feed(left, [[0.4, 0]]);
    assert.ok(left.done());
    const right = new LivenessTracker(['turn-right']);
    feed(right, [[-0.5, 0]]);
    assert.ok(!right.done(), 'already turned at the start: no frontal frame yet');
    feed(right, [[0, 0], [-0.35, 0]]);
    assert.ok(right.done());
});

test('smile: neutral then happy; a smiling photo does not pass', () => {
    const t = new LivenessTracker(['smile']);
    feed(t, [[0, 0.1], [0, 0.95]]);
    assert.ok(t.done());
    const photo = new LivenessTracker(['smile']);
    feed(photo, Array(20).fill([0, 0.99]));
    assert.ok(!photo.done());
});

test('challenges are performed in the order given, each with its own movement', () => {
    const t = new LivenessTracker(['turn-right', 'smile', 'turn-left']);
    feed(t, [[0, 0.1], [0, 0.9], [0.5, 0]]);              // a smile or a left turn first does not count for turn-right
    assert.strictEqual(t.current(), 'turn-right');
    feed(t, [[0, 0], [-0.4, 0], [0, 0.1], [0, 0.9], [0, 0], [0.4, 0]]);
    assert.ok(t.done());
    assert.deepStrictEqual(t.results().map(r => r.name), ['turn-right', 'smile', 'turn-left']);
    assert.ok(t.results().every(r => r.passed));
});

test('thresholds can be overridden; unknown challenges are refused', () => {
    const t = new LivenessTracker(['turn-left'], { turnYaw: 0.6 });
    feed(t, [[0, 0], [0.4, 0]]);
    assert.ok(!t.done());
    assert.throws(() => new LivenessTracker(['nod']), /Unknown liveness challenge/);
    assert.throws(() => new LivenessTracker([]), /at least one/);
});

test('passkey base64url helpers round-trip binary data', () => {
    const bytes = new Uint8Array(Array.from({ length: 300 }, (_, i) => (i * 37) % 256));
    const text = PasskeyClient.toBase64Url(bytes.buffer);
    assert.ok(/^[A-Za-z0-9_-]+$/.test(text));
    assert.deepStrictEqual(new Uint8Array(PasskeyClient.toBuffer(text)), bytes);
    assert.strictEqual(PasskeyClient.toBase64Url(PasskeyClient.toBuffer('AQID')), 'AQID');
});

console.log(`  ${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
