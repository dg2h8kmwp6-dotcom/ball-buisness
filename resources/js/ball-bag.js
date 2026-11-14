/**
 * An animated bag of pit balls.
 *
 * Renders a sampled handful of balls rather than every ball ordered: a mix of
 * 20,000 balls is drawn as ~160 balls that keep the same colour proportions,
 * so the bag shows the right blend and a roughly proportional fill level.
 */

const MAX_RENDERED_BALLS = 170;
const GRAVITY = 1500;
const WALL_RESTITUTION = 0.4;
const FLOOR_RESTITUTION = 0.32;
const BALL_RESTITUTION = 0.22;
const AIR_DAMPING = 0.994;
const FLOOR_FRICTION = 0.9;
const FIXED_STEP = 1 / 120;
const MAX_STEPS_PER_FRAME = 8;
const REMOVAL_DURATION = 0.22;

/** Ink used for the bag outline, matching the page's border colour. */
const INK = '#15100b';
const POUR_DURATION = 0.9;

/**
 * A dense pile of balls jitters indefinitely under positional correction, so
 * velocity alone never reaches a true rest. Stop simulating this long after
 * the last change instead — by then the pile has visually settled, and the
 * render loop can idle until the next change.
 */
const SETTLE_TIMEOUT = 3.5;

/**
 * Shuffle in place (Fisher-Yates).
 *
 * @param {Array<unknown>} items
 */
function shuffle(items) {
    for (let i = items.length - 1; i > 0; i -= 1) {
        const j = Math.floor(Math.random() * (i + 1));
        [items[i], items[j]] = [items[j], items[i]];
    }
}

/**
 * Share the rendered-ball budget out across colours, preserving proportions.
 *
 * Below the budget every ordered ball gets its own rendered ball, so the fill
 * level rises with the order. Above it the bag is simply full, and the caller
 * reports how many real balls each rendered ball stands for.
 *
 * Uses largest-remainder rounding so the counts sum to the budget exactly, and
 * guarantees every ordered colour at least one ball so a small accent colour
 * never disappears from the mix.
 *
 * @param {Array<{id: string, color: string, quantity: number}>} mix
 * @param {number} budget total rendered balls to allocate
 * @returns {Map<string, number>} ball type id => rendered ball count
 */
function allocateRenderedCounts(mix, budget) {
    const counts = new Map();

    const total = mix.reduce((sum, entry) => sum + entry.quantity, 0);

    if (total <= 0 || budget <= 0) {
        return counts;
    }

    const remainders = [];
    let assigned = 0;

    for (const entry of mix) {
        const exact = (entry.quantity / total) * budget;
        const base = Math.floor(exact);

        counts.set(entry.id, base);
        assigned += base;
        remainders.push({ id: entry.id, remainder: exact - base });
    }

    // Hand the rounding leftovers to the colours that lost the most.
    remainders.sort((a, b) => b.remainder - a.remainder);

    let leftover = budget - assigned;

    for (const { id } of remainders) {
        if (leftover <= 0) {
            break;
        }

        counts.set(id, counts.get(id) + 1);
        leftover -= 1;
    }

    // Borrow a ball for any colour that rounded away to nothing.
    for (const [id, count] of counts) {
        if (count > 0) {
            continue;
        }

        const donor = [...counts.entries()].sort((a, b) => b[1] - a[1])[0];

        if (!donor || donor[1] < 2) {
            break;
        }

        counts.set(donor[0], donor[1] - 1);
        counts.set(id, 1);
    }

    return counts;
}

/**
 * Loaded ball photos, keyed by URL and shared across bag instances.
 *
 * @type {Map<string, {element: HTMLImageElement, loaded: boolean, failed: boolean}>}
 */
const imageCache = new Map();

/**
 * Begin loading a ball photo, calling back once it is ready to draw.
 *
 * Returns immediately with the cache entry so callers can fall back to the
 * flat colour until the photo arrives.
 *
 * @param {string} url
 * @param {() => void} onReady
 */
function loadImage(url, onReady) {
    const cached = imageCache.get(url);

    if (cached) {
        if (!cached.loaded && !cached.failed) {
            cached.element.addEventListener('load', onReady, { once: true });
        }

        return cached;
    }

    const entry = { element: new Image(), loaded: false, failed: false };

    entry.element.addEventListener('load', () => {
        entry.loaded = true;
        onReady();
    });

    entry.element.addEventListener('error', () => {
        entry.failed = true;
    });

    entry.element.src = url;
    imageCache.set(url, entry);

    return entry;
}

/**
 * Pre-render a ball photo into a round sprite.
 *
 * The stored photo is a plain square, so the circle is applied here rather
 * than baked into the file.
 *
 * @param {HTMLImageElement} image
 * @param {number} radius
 * @param {number} dpr
 * @returns {HTMLCanvasElement}
 */
function createPhotoSprite(image, radius, dpr) {
    const padding = 2;
    const size = Math.ceil((radius + padding) * 2 * dpr);
    const sprite = document.createElement('canvas');
    sprite.width = size;
    sprite.height = size;

    const ctx = sprite.getContext('2d');
    ctx.scale(dpr, dpr);

    const center = radius + padding;

    ctx.save();
    ctx.beginPath();
    ctx.arc(center, center, radius, 0, Math.PI * 2);
    ctx.clip();

    // Cover-fit the photo inside the circle.
    const natural = Math.min(image.naturalWidth || 1, image.naturalHeight || 1);
    const drawScale = (radius * 2) / natural;
    const width = (image.naturalWidth || 1) * drawScale;
    const height = (image.naturalHeight || 1) * drawScale;

    ctx.drawImage(image, center - width / 2, center - height / 2, width, height);
    ctx.restore();

    // Match the shading the generated balls get, so a mix of photographed and
    // flat-colour balls still reads as one bag.
    ctx.beginPath();
    ctx.arc(center, center, radius - 0.5, 0, Math.PI * 2);
    ctx.strokeStyle = 'rgba(21, 16, 11, 0.55)';
    ctx.lineWidth = 1.25;
    ctx.stroke();

    return sprite;
}

/**
 * Pre-render a glossy plastic ball to an offscreen canvas.
 *
 * Drawing 170 cached sprites per frame is far cheaper than building 170 radial
 * gradients per frame.
 *
 * @param {string} color
 * @param {number} radius
 * @param {number} dpr
 * @returns {HTMLCanvasElement}
 */
function createBallSprite(color, radius, dpr) {
    const padding = 2;
    const size = Math.ceil((radius + padding) * 2 * dpr);
    const sprite = document.createElement('canvas');
    sprite.width = size;
    sprite.height = size;

    const ctx = sprite.getContext('2d');
    ctx.scale(dpr, dpr);

    const center = radius + padding;

    const body = ctx.createRadialGradient(
        center - radius * 0.35,
        center - radius * 0.4,
        radius * 0.1,
        center,
        center,
        radius,
    );
    body.addColorStop(0, 'rgba(255, 255, 255, 0.72)');
    body.addColorStop(0.3, color);
    body.addColorStop(1, 'rgba(0, 0, 0, 0.18)');

    ctx.beginPath();
    ctx.arc(center, center, radius, 0, Math.PI * 2);
    ctx.fillStyle = color;
    ctx.fill();
    ctx.fillStyle = body;
    ctx.fill();

    // Rim shading keeps adjacent same-colour balls readable as separate balls.
    ctx.beginPath();
    ctx.arc(center, center, radius - 0.5, 0, Math.PI * 2);
    ctx.strokeStyle = 'rgba(21, 16, 11, 0.55)';
    ctx.lineWidth = 1.25;
    ctx.stroke();

    // Specular highlight.
    ctx.beginPath();
    ctx.ellipse(
        center - radius * 0.34,
        center - radius * 0.42,
        radius * 0.26,
        radius * 0.18,
        -Math.PI / 5,
        0,
        Math.PI * 2,
    );
    ctx.fillStyle = 'rgba(255, 255, 255, 0.78)';
    ctx.fill();

    return sprite;
}

/**
 * Mount the animated bag onto a canvas.
 *
 * @param {HTMLCanvasElement} canvas
 * @param {{onStats?: (stats: {scale: number, rendered: number, quantity: number}) => void}} [options]
 */
export function createBallBag(canvas, options = {}) {
    const ctx = canvas.getContext('2d');
    const onStats = options.onStats ?? (() => {});
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /** @type {Array<{x: number, y: number, vx: number, vy: number, color: string, styleKey: string, typeId: string, spawnAt: number, removingAt: number|null}>} */
    let balls = [];
    /** @type {Map<string, HTMLCanvasElement>} */
    let sprites = new Map();
    /** @type {Array<{id: string, color: string, image?: string|null, quantity: number}>} */
    let currentMix = [];

    let width = 0;
    let height = 0;
    let dpr = 1;
    let radius = 12;
    let bag = null;
    let clock = 0;
    let spawnCursor = 0;
    let lastChangeAt = 0;
    let frame = null;
    let lastTimestamp = null;

    function measure() {
        const rect = canvas.getBoundingClientRect();
        const nextWidth = Math.max(rect.width, 1);
        const nextHeight = Math.max(rect.height, 1);
        const nextDpr = Math.min(window.devicePixelRatio || 1, 2);

        const previous = bag;

        width = nextWidth;
        height = nextHeight;
        dpr = nextDpr;

        canvas.width = Math.round(width * dpr);
        canvas.height = Math.round(height * dpr);
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

        bag = {
            centerX: width / 2,
            topY: height * 0.26,
            bottomY: height * 0.94,
            halfTop: width * 0.37,
            halfBottom: width * 0.305,
            cornerRadius: Math.min(width, height) * 0.11,
        };

        radius = Math.min(
            22,
            Math.max(
                5,
                Math.sqrt((0.66 * width * (bag.bottomY - bag.topY)) / (MAX_RENDERED_BALLS * Math.PI)),
            ),
        );

        rebuildSprites();
        lastChangeAt = clock;

        // Keep the pile sensible across resizes by scaling positions with the bag.
        if (previous && balls.length > 0) {
            const scaleX = width / Math.max(previous.centerX * 2, 1);
            const scaleY = (bag.bottomY - bag.topY) / Math.max(previous.bottomY - previous.topY, 1);

            for (const ball of balls) {
                ball.x = ball.x * scaleX;
                ball.y = bag.bottomY - (previous.bottomY - ball.y) * scaleY;
            }
        }

    }

    /**
     * The sprite cache key for a mix entry: its photo when it has one, and
     * otherwise its flat colour.
     *
     * @param {{color: string, image?: string|null}} entry
     */
    function styleKeyFor(entry) {
        return entry.image ? `photo:${entry.image}` : `color:${entry.color}`;
    }

    function rebuildSprites() {
        sprites = new Map();

        for (const entry of currentMix) {
            const key = styleKeyFor(entry);

            if (sprites.has(key)) {
                continue;
            }

            if (entry.image) {
                // Repaint once the photo lands; until then, show the colour.
                const cached = loadImage(entry.image, () => {
                    rebuildSprites();
                    ensureRunning();
                });

                if (cached.loaded) {
                    sprites.set(key, createPhotoSprite(cached.element, radius, dpr));

                    continue;
                }
            }

            sprites.set(key, createBallSprite(entry.color, radius, dpr));
        }
    }

    /**
     * Half-width of the bag interior at a given y, including the rounded base.
     */
    function halfWidthAt(y) {
        const span = bag.bottomY - bag.topY;
        const t = Math.min(Math.max((bag.bottomY - y) / span, 0), 1);
        let half = bag.halfBottom + (bag.halfTop - bag.halfBottom) * t;

        const cornerStart = bag.bottomY - bag.cornerRadius;

        if (y > cornerStart) {
            const dy = y - cornerStart;
            const inset = bag.cornerRadius - Math.sqrt(Math.max(bag.cornerRadius ** 2 - dy ** 2, 0));
            half -= inset;
        }

        return Math.max(half, radius);
    }

    function bagPath(inset = 0) {
        const path = new Path2D();
        const { centerX, topY, bottomY, halfTop, halfBottom, cornerRadius } = bag;
        const left = centerX - halfTop + inset;
        const right = centerX + halfTop - inset;
        const bottomLeft = centerX - halfBottom + inset;
        const bottomRight = centerX + halfBottom - inset;
        const r = Math.max(cornerRadius - inset, 0);

        path.moveTo(left, topY);
        path.lineTo(bottomLeft, bottomY - r);
        path.quadraticCurveTo(bottomLeft, bottomY, bottomLeft + r, bottomY);
        path.lineTo(bottomRight - r, bottomY);
        path.quadraticCurveTo(bottomRight, bottomY, bottomRight, bottomY - r);
        path.lineTo(right, topY);
        path.closePath();

        return path;
    }

    /**
     * Reconcile the on-screen balls with the requested mix.
     *
     * @param {Array<{id: string, color: string, quantity: number}>} mix
     */
    function setMix(mix) {
        currentMix = mix.filter((entry) => entry.quantity > 0);
        lastChangeAt = clock;

        const quantity = currentMix.reduce((total, entry) => total + entry.quantity, 0);
        const budget = Math.min(quantity, MAX_RENDERED_BALLS);
        const targets = allocateRenderedCounts(currentMix, budget);

        rebuildSprites();

        const styles = new Map(
            currentMix.map((entry) => [entry.id, { color: entry.color, styleKey: styleKeyFor(entry) }]),
        );

        // A ball type may be recoloured or given a photo between renders;
        // keep the balls already in the bag in step.
        for (const ball of balls) {
            const style = styles.get(ball.typeId);

            if (style) {
                ball.color = style.color;
                ball.styleKey = style.styleKey;
            }
        }

        const liveByType = new Map();

        for (const ball of balls) {
            if (ball.removingAt !== null) {
                continue;
            }

            if (!liveByType.has(ball.typeId)) {
                liveByType.set(ball.typeId, []);
            }

            liveByType.get(ball.typeId).push(ball);
        }

        // Retire balls whose colour is no longer in the mix.
        for (const [typeId, group] of liveByType) {
            if (!targets.has(typeId)) {
                group.forEach(retire);
            }
        }

        /** @type {Array<string>} ball type ids to pour in, one entry per ball */
        const toSpawn = [];

        for (const [typeId, target] of targets) {
            const group = liveByType.get(typeId) ?? [];
            const delta = target - group.length;

            if (delta > 0) {
                for (let i = 0; i < delta; i += 1) {
                    toSpawn.push(typeId);
                }
            } else if (delta < 0) {
                // Deflate from the top of the pile so the bag settles downward.
                group
                    .slice()
                    .sort((a, b) => a.y - b.y)
                    .slice(0, -delta)
                    .forEach(retire);
            }
        }

        if (toSpawn.length > 0) {
            // Interleave the colours so the mix pours in blended rather than
            // in solid-coloured layers.
            shuffle(toSpawn);

            // Spread the whole batch over roughly a second, whatever its size.
            const interval = Math.min(Math.max(POUR_DURATION / toSpawn.length, 0.005), 0.05);

            for (const typeId of toSpawn) {
                spawn(typeId, styles.get(typeId), interval);
            }
        }

        let rendered = 0;
        for (const count of targets.values()) {
            rendered += count;
        }

        onStats({
            scale: rendered > 0 ? quantity / rendered : 1,
            rendered,
            quantity,
        });

        if (reducedMotion) {
            settleInstantly();
        }

        ensureRunning();
    }

    function retire(ball) {
        ball.removingAt = clock;
    }

    function spawn(typeId, style, interval) {
        // Queue each ball one interval behind the last, so they pour in rather
        // than appearing all at once.
        const spawnAt = Math.max(clock, spawnCursor + interval);
        spawnCursor = spawnAt;

        const opening = bag.halfTop * 0.72;

        balls.push({
            typeId,
            color: style.color,
            styleKey: style.styleKey,
            x: bag.centerX + (Math.random() * 2 - 1) * opening,
            y: -radius - Math.random() * height * 0.25,
            vx: (Math.random() * 2 - 1) * 40,
            vy: 120 + Math.random() * 80,
            spawnAt,
            removingAt: null,
        });
    }

    /**
     * Fast-forward the simulation so the pile is already settled, for viewers
     * who have asked for reduced motion.
     *
     * Starts the balls just above the rim rather than high overhead, so far
     * fewer steps are needed to reach a settled pile.
     */
    function settleInstantly() {
        for (const ball of balls) {
            ball.spawnAt = 0;

            if (ball.y < bag.topY) {
                ball.y = bag.topY - radius - Math.random() * radius * 6;
                ball.vy = 60;
            }
        }

        spawnCursor = clock;

        for (let i = 0; i < 260; i += 1) {
            step(FIXED_STEP);
        }
    }

    function step(dt) {
        clock += dt;

        // Drop balls that have finished their removal animation.
        if (balls.some((ball) => ball.removingAt !== null && clock - ball.removingAt >= REMOVAL_DURATION)) {
            balls = balls.filter(
                (ball) => ball.removingAt === null || clock - ball.removingAt < REMOVAL_DURATION,
            );
        }

        const active = balls.filter((ball) => ball.spawnAt <= clock);

        for (const ball of active) {
            ball.vy += GRAVITY * dt;
            ball.vx *= AIR_DAMPING;
            ball.vy *= AIR_DAMPING;
            ball.x += ball.vx * dt;
            ball.y += ball.vy * dt;
        }

        resolveCollisions(active);

        for (const ball of active) {
            constrainToBag(ball);
        }
    }

    function constrainToBag(ball) {
        // Balls fall in through the open top, so only constrain once they are
        // below the rim.
        if (ball.y + radius < bag.topY) {
            return;
        }

        const half = halfWidthAt(ball.y);
        const left = bag.centerX - half + radius;
        const right = bag.centerX + half - radius;

        if (ball.x < left) {
            ball.x = left;
            ball.vx = Math.abs(ball.vx) * WALL_RESTITUTION;
        } else if (ball.x > right) {
            ball.x = right;
            ball.vx = -Math.abs(ball.vx) * WALL_RESTITUTION;
        }

        const floor = bag.bottomY - radius;

        if (ball.y > floor) {
            ball.y = floor;

            if (ball.vy > 0) {
                ball.vy = -ball.vy * FLOOR_RESTITUTION;
            }

            ball.vx *= FLOOR_FRICTION;
        }
    }

    function resolveCollisions(active) {
        if (active.length < 2) {
            return;
        }

        const cellSize = radius * 2;
        const grid = new Map();

        const key = (cx, cy) => `${cx}:${cy}`;

        for (const ball of active) {
            const cx = Math.floor(ball.x / cellSize);
            const cy = Math.floor(ball.y / cellSize);
            const cellKey = key(cx, cy);

            if (!grid.has(cellKey)) {
                grid.set(cellKey, []);
            }

            grid.get(cellKey).push(ball);
        }

        const minDistance = radius * 2;

        for (const ball of active) {
            const cx = Math.floor(ball.x / cellSize);
            const cy = Math.floor(ball.y / cellSize);

            for (let ox = -1; ox <= 1; ox += 1) {
                for (let oy = -1; oy <= 1; oy += 1) {
                    const cell = grid.get(key(cx + ox, cy + oy));

                    if (!cell) {
                        continue;
                    }

                    for (const other of cell) {
                        if (other === ball) {
                            continue;
                        }

                        let dx = other.x - ball.x;
                        let dy = other.y - ball.y;
                        let distance = Math.hypot(dx, dy);

                        if (distance >= minDistance) {
                            continue;
                        }

                        if (distance === 0) {
                            dx = Math.random() - 0.5;
                            dy = Math.random() - 0.5;
                            distance = Math.hypot(dx, dy) || 1;
                        }

                        const nx = dx / distance;
                        const ny = dy / distance;
                        const overlap = (minDistance - distance) / 2;

                        ball.x -= nx * overlap;
                        ball.y -= ny * overlap;
                        other.x += nx * overlap;
                        other.y += ny * overlap;

                        const relative = (other.vx - ball.vx) * nx + (other.vy - ball.vy) * ny;

                        if (relative < 0) {
                            const impulse = relative * (1 + BALL_RESTITUTION) * 0.5;
                            ball.vx += impulse * nx;
                            ball.vy += impulse * ny;
                            other.vx -= impulse * nx;
                            other.vy -= impulse * ny;
                        }
                    }
                }
            }
        }
    }

    function draw() {
        ctx.clearRect(0, 0, width, height);

        const interior = bagPath();

        // Bag interior.
        ctx.save();
        ctx.fillStyle = 'rgba(255, 255, 255, 0.85)';
        ctx.fill(interior);
        ctx.restore();

        // Balls, clipped so they read as being inside the bag.
        ctx.save();
        const clip = new Path2D();
        clip.rect(0, 0, width, bag.topY);
        clip.addPath(interior);
        ctx.clip(clip, 'nonzero');

        for (const ball of balls) {
            if (ball.spawnAt > clock) {
                continue;
            }

            const sprite = sprites.get(ball.styleKey);

            if (!sprite) {
                continue;
            }

            let scale = 1;

            if (ball.removingAt !== null) {
                scale = Math.max(1 - (clock - ball.removingAt) / REMOVAL_DURATION, 0);
            }

            const drawRadius = (radius + 2) * scale;

            ctx.drawImage(
                sprite,
                ball.x - drawRadius,
                ball.y - drawRadius,
                drawRadius * 2,
                drawRadius * 2,
            );
        }

        ctx.restore();

        drawBagShell(interior);
    }

    function drawBagShell(interior) {
        const { centerX, topY, bottomY, halfTop } = bag;

        // Frosted front face over the balls.
        ctx.save();
        const sheen = ctx.createLinearGradient(centerX - halfTop, topY, centerX + halfTop, bottomY);
        sheen.addColorStop(0, 'rgba(255, 255, 255, 0.34)');
        sheen.addColorStop(0.45, 'rgba(255, 255, 255, 0.08)');
        sheen.addColorStop(1, 'rgba(255, 255, 255, 0.26)');
        ctx.fillStyle = sheen;
        ctx.fill(interior);
        ctx.restore();

        // Bag outline.
        ctx.save();
        ctx.strokeStyle = INK;
        ctx.lineWidth = 3;
        ctx.lineJoin = 'round';
        ctx.stroke(interior);
        ctx.restore();

        // Rim.
        ctx.save();
        ctx.beginPath();
        ctx.ellipse(centerX, topY, halfTop, halfTop * 0.17, 0, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(255, 255, 255, 0.6)';
        ctx.fill();
        ctx.strokeStyle = INK;
        ctx.lineWidth = 3;
        ctx.stroke();
        ctx.restore();

        // Handles.
        ctx.save();
        ctx.strokeStyle = INK;
        ctx.lineWidth = 4.5;
        ctx.lineCap = 'round';

        for (const direction of [-1, 1]) {
            ctx.beginPath();
            ctx.moveTo(centerX + direction * halfTop * 0.62, topY - 2);
            ctx.quadraticCurveTo(
                centerX + direction * halfTop * 0.4,
                topY - halfTop * 0.52,
                centerX + direction * halfTop * 0.14,
                topY - 2,
            );
            ctx.stroke();
        }

        ctx.restore();
    }

    function isAtRest() {
        // Still pouring in or fading out, so there is visible motion left.
        if (balls.some((ball) => ball.removingAt !== null || ball.spawnAt > clock)) {
            return false;
        }

        if (balls.every((ball) => Math.abs(ball.vx) < 1.5 && Math.abs(ball.vy) < 1.5)) {
            return true;
        }

        return clock - lastChangeAt > SETTLE_TIMEOUT;
    }

    function tick(timestamp) {
        frame = null;

        if (lastTimestamp === null) {
            lastTimestamp = timestamp;
        }

        const elapsed = Math.min((timestamp - lastTimestamp) / 1000, 0.25);
        lastTimestamp = timestamp;

        if (!reducedMotion) {
            const steps = Math.min(Math.ceil(elapsed / FIXED_STEP), MAX_STEPS_PER_FRAME);

            for (let i = 0; i < steps; i += 1) {
                step(FIXED_STEP);
            }
        }

        draw();

        // Idle once the pile has settled; the next mix change wakes it up.
        if (reducedMotion || isAtRest()) {
            lastTimestamp = null;

            return;
        }

        frame = requestAnimationFrame(tick);
    }

    function ensureRunning() {
        if (frame === null) {
            lastTimestamp = null;
            frame = requestAnimationFrame(tick);
        }
    }

    const resizeObserver = new ResizeObserver(() => {
        measure();
        ensureRunning();
    });

    measure();
    resizeObserver.observe(canvas);
    draw();

    return {
        setMix,
        destroy() {
            resizeObserver.disconnect();

            if (frame !== null) {
                cancelAnimationFrame(frame);
                frame = null;
            }
        },
    };
}
