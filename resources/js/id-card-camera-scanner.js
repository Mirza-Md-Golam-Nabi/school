/**
 * Reads a QR code from the device camera for the fee counter's "Scan ID Card"
 * box. Uses the browser's own BarcodeDetector where it reads QR codes (Chrome
 * on Android) and jsQR everywhere else (Windows laptops, iPhone). Loaded on
 * demand, only when someone taps the camera button.
 */
import jsQR from 'jsqr';

const SCAN_INTERVAL_MS = 150;
const MAX_FRAME_WIDTH = 640;

async function makeNativeDetector() {
    if (!('BarcodeDetector' in window)) {
        return null;
    }

    try {
        const formats = await window.BarcodeDetector.getSupportedFormats();

        return formats.includes('qr_code') ? new window.BarcodeDetector({ formats: ['qr_code'] }) : null;
    } catch {
        return null;
    }
}

/**
 * Start the camera on the given <video> and call onResult once with the first
 * QR code's text. Resolves to a function that stops the camera.
 *
 * @param {HTMLVideoElement} video
 * @param {(text: string) => void} onResult
 * @returns {Promise<() => void>}
 */
export async function startQrScan(video, onResult) {
    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
        const error = new Error('Camera needs a secure (HTTPS) page.');
        error.name = 'InsecureContextError';
        throw error;
    }

    const stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: 'environment' } },
        audio: false,
    });

    let stopped = false;
    let timer = null;

    const stop = () => {
        stopped = true;
        clearTimeout(timer);
        stream.getTracks().forEach((track) => track.stop());
        video.srcObject = null;
    };

    video.setAttribute('playsinline', '');
    video.muted = true;
    video.srcObject = stream;
    await video.play();

    const detector = await makeNativeDetector();
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d', { willReadFrequently: true });

    const readFrame = async () => {
        if (video.readyState < video.HAVE_ENOUGH_DATA || !video.videoWidth) {
            return null;
        }

        if (detector) {
            const codes = await detector.detect(video);

            return codes[0]?.rawValue ?? null;
        }

        const scale = Math.min(1, MAX_FRAME_WIDTH / video.videoWidth);
        canvas.width = Math.round(video.videoWidth * scale);
        canvas.height = Math.round(video.videoHeight * scale);
        context.drawImage(video, 0, 0, canvas.width, canvas.height);

        const frame = context.getImageData(0, 0, canvas.width, canvas.height);

        return jsQR(frame.data, frame.width, frame.height, { inversionAttempts: 'dontInvert' })?.data ?? null;
    };

    const tick = async () => {
        if (stopped) {
            return;
        }

        let text = null;

        try {
            text = await readFrame();
        } catch {
            text = null;
        }

        if (stopped) {
            return;
        }

        if (text) {
            stop();
            onResult(text);

            return;
        }

        timer = setTimeout(tick, SCAN_INTERVAL_MS);
    };

    tick();

    return stop;
}
