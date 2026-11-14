/**
 * A small square/circular image cropper for the admin ball photo field.
 *
 * The admin drags and zooms the photo inside a square viewport with a circular
 * guide. On submit we post the crop rectangle in *source-image pixels*, and
 * the server does the real crop with GD, so no image library is needed on
 * either side.
 */

/** Keep the crop from exceeding the source image. */
const MAX_ZOOM = 4;

export function initBallPhotoCropper(form) {
    const input = form.querySelector('[data-photo-input]');
    const cropper = form.querySelector('[data-cropper]');

    if (!input || !cropper) {
        return;
    }

    const viewport = cropper.querySelector('[data-cropper-viewport]');
    const image = cropper.querySelector('[data-cropper-image]');
    const zoomInput = cropper.querySelector('[data-cropper-zoom]');
    const resetButton = cropper.querySelector('[data-cropper-reset]');
    const cropX = cropper.querySelector('[data-crop-x]');
    const cropY = cropper.querySelector('[data-crop-y]');
    const cropSize = cropper.querySelector('[data-crop-size]');

    /** Scale at zoom 1: the smallest that still covers the viewport. */
    let baseScale = 1;
    let zoom = 1;
    /** Image top-left offset within the viewport, in CSS pixels. */
    let offsetX = 0;
    let offsetY = 0;
    let objectUrl = null;

    const viewportSize = () => viewport.clientWidth;

    function scale() {
        return baseScale * zoom;
    }

    /** Keep the image covering the viewport on both axes. */
    function clampOffsets() {
        const size = viewportSize();
        const width = image.naturalWidth * scale();
        const height = image.naturalHeight * scale();

        offsetX = Math.min(0, Math.max(size - width, offsetX));
        offsetY = Math.min(0, Math.max(size - height, offsetY));
    }

    function render() {
        clampOffsets();

        image.style.transform = `translate(${offsetX}px, ${offsetY}px) scale(${scale()})`;

        // Translate the view back into source-image pixels for the server.
        const size = viewportSize();
        const sourceSize = Math.round(size / scale());

        cropX.value = String(Math.max(0, Math.round(-offsetX / scale())));
        cropY.value = String(Math.max(0, Math.round(-offsetY / scale())));
        cropSize.value = String(
            Math.max(1, Math.min(sourceSize, image.naturalWidth, image.naturalHeight)),
        );
    }

    function fit() {
        const size = viewportSize();

        baseScale = Math.max(size / image.naturalWidth, size / image.naturalHeight);
        zoom = 1;
        zoomInput.value = '1';

        // Start centred.
        offsetX = (size - image.naturalWidth * baseScale) / 2;
        offsetY = (size - image.naturalHeight * baseScale) / 2;

        render();
    }

    function zoomTo(nextZoom) {
        const size = viewportSize();
        const previous = scale();

        zoom = Math.min(Math.max(nextZoom, 1), MAX_ZOOM);

        // Zoom about the centre of the viewport so the framing holds.
        const ratio = scale() / previous;
        offsetX = size / 2 - (size / 2 - offsetX) * ratio;
        offsetY = size / 2 - (size / 2 - offsetY) * ratio;

        render();
    }

    input.addEventListener('change', () => {
        const file = input.files?.[0];

        if (!file) {
            cropper.hidden = true;
            cropSize.value = '0';

            return;
        }

        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
        }

        objectUrl = URL.createObjectURL(file);

        image.onload = () => {
            cropper.hidden = false;
            // The viewport has no width until it is shown.
            fit();
        };

        image.src = objectUrl;
    });

    // Transform origin is the top-left, so width/height stay natural size.
    image.style.width = 'auto';
    image.style.height = 'auto';

    zoomInput.addEventListener('input', () => {
        zoomTo(Number(zoomInput.value));
    });

    resetButton?.addEventListener('click', () => {
        fit();
    });

    // Drag to pan, with pointer events so touch and mouse share one path.
    let dragging = false;
    let startX = 0;
    let startY = 0;

    viewport.addEventListener('pointerdown', (event) => {
        if (cropper.hidden) {
            return;
        }

        dragging = true;
        startX = event.clientX - offsetX;
        startY = event.clientY - offsetY;
        viewport.setPointerCapture(event.pointerId);
    });

    viewport.addEventListener('pointermove', (event) => {
        if (!dragging) {
            return;
        }

        offsetX = event.clientX - startX;
        offsetY = event.clientY - startY;
        render();
    });

    for (const type of ['pointerup', 'pointercancel']) {
        viewport.addEventListener(type, (event) => {
            dragging = false;

            if (viewport.hasPointerCapture(event.pointerId)) {
                viewport.releasePointerCapture(event.pointerId);
            }
        });
    }

    viewport.addEventListener(
        'wheel',
        (event) => {
            if (cropper.hidden) {
                return;
            }

            event.preventDefault();

            const next = zoom * (event.deltaY < 0 ? 1.08 : 1 / 1.08);
            zoomInput.value = String(Math.min(Math.max(next, 1), MAX_ZOOM));
            zoomTo(next);
        },
        { passive: false },
    );

    // Re-fit if the viewport changes size (orientation change, window resize).
    new ResizeObserver(() => {
        if (!cropper.hidden && image.naturalWidth > 0) {
            render();
        }
    }).observe(viewport);

    window.addEventListener('beforeunload', () => {
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
        }
    });
}
