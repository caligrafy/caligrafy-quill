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

// 68 landmarks of a face: eyes open by `open` (0..1) and the nose shifted by `yaw` (-1..1)
function landmarks(open = 1, yaw = 0) {
    const p = Array.from({ length: 68 }, () => ({ x: 50, y: 50 }));
    p[0] = { x: 0, y: 40 }; p[16] = { x: 100, y: 40 };
    p[30] = { x: 50 + yaw * 50, y: 55 };
    const eye = (x0) => {
        const h = 6 * open;   // eye 20 wide: EAR = h / 20
        return [{ x: x0, y: 30 }, { x: x0 + 7, y: 30 - h / 2 }, { x: x0 + 13, y: 30 - h / 2 }, { x: x0 + 20, y: 30 }, { x: x0 + 13, y: 30 + h / 2 }, { x: x0 + 7, y: 30 + h / 2 }];
    };
    eye(20).forEach((q, i) => p[36 + i] = q);
    eye(60).forEach((q, i) => p[42 + i] = q);
    return p;
}
const frame = (open, yaw, happy, at) => FaceDetector.frameSummary(landmarks(open, yaw), { happy: happy }, at);
const feed = (tracker, frames) => frames.forEach((f, i) => tracker.add(frame(f[0], f[1], f[2], i * 100)));

test('eye aspect ratio: open about 0.3, closed near 0', () => {
    assert.ok(Math.abs(FaceDetector.eyeAspectRatio(landmarks(1).slice(36, 42)) - 0.3) < 0.001);
    assert.ok(FaceDetector.eyeAspectRatio(landmarks(0.1).slice(36, 42)) < 0.05);
    assert.strictEqual(FaceDetector.eyeAspectRatio([{ x: 1, y: 1 }, { x: 1, y: 1 }, { x: 1, y: 1 }, { x: 1, y: 1 }, { x: 1, y: 1 }, { x: 1, y: 1 }]), 0);
});

test('head yaw: 0 frontal, > 0 to the person\'s left (nose towards jaw point 16), < 0 to the right', () => {
    assert.strictEqual(FaceDetector.headYaw(landmarks(1, 0)), 0);
    assert.ok(Math.abs(FaceDetector.headYaw(landmarks(1, 0.5)) - 0.5) < 1e-9);
    assert.ok(FaceDetector.headYaw(landmarks(1, -0.4)) < -0.39);
});

test('blink: open, closed, open again passes', () => {
    const t = new LivenessTracker(['blink']);
    feed(t, [[1, 0, 0], [1, 0, 0], [0.2, 0, 0], [1, 0, 0]]);
    assert.ok(t.done());
    assert.deepStrictEqual(t.results(), [{ name: 'blink', passed: true, at: 300 }]);
});

test('blink: a photo (eyes always open) or eyes that stay closed do not pass', () => {
    const photo = new LivenessTracker(['blink']);
    feed(photo, Array(50).fill([1, 0, 0]));
    assert.ok(!photo.done());
    const closed = new LivenessTracker(['blink']);
    feed(closed, [[1, 0, 0], [0.2, 0, 0], [0.2, 0, 0], [0.5, 0, 0]]);
    assert.ok(!closed.done());
});

test('turn-left / turn-right: start facing the camera, then turn the right way', () => {
    const left = new LivenessTracker(['turn-left']);
    feed(left, [[1, 0.05, 0], [1, -0.5, 0]]);
    assert.ok(!left.done(), 'a turn to the right is not a turn to the left');
    feed(left, [[1, 0.4, 0]]);
    assert.ok(left.done());
    const right = new LivenessTracker(['turn-right']);
    feed(right, [[1, -0.5, 0]]);
    assert.ok(!right.done(), 'already turned at the start: no frontal frame yet');
    feed(right, [[1, 0, 0], [1, -0.35, 0]]);
    assert.ok(right.done());
});

test('smile: neutral then happy; a smiling photo does not pass', () => {
    const t = new LivenessTracker(['smile']);
    feed(t, [[1, 0, 0.1], [1, 0, 0.95]]);
    assert.ok(t.done());
    const photo = new LivenessTracker(['smile']);
    feed(photo, Array(20).fill([1, 0, 0.99]));
    assert.ok(!photo.done());
});

test('challenges are performed in the order given, each with its own movement', () => {
    const t = new LivenessTracker(['turn-right', 'blink', 'smile']);
    feed(t, [[1, 0, 0.9], [0.2, 0, 0], [1, 0, 0]]);       // a blink first does not count for turn-right
    assert.strictEqual(t.current(), 'turn-right');
    feed(t, [[1, -0.4, 0], [1, 0, 0], [0.2, 0, 0], [1, 0, 0], [1, 0, 0.1], [1, 0, 0.9]]);
    assert.ok(t.done());
    assert.deepStrictEqual(t.results().map(r => r.name), ['turn-right', 'blink', 'smile']);
    assert.ok(t.results().every(r => r.passed));
});

test('thresholds can be overridden; unknown challenges are refused', () => {
    const t = new LivenessTracker(['turn-left'], { turnYaw: 0.6 });
    feed(t, [[1, 0, 0], [1, 0.4, 0]]);
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
