/**
 * An animated ball pit.
 *
 * Balls are simulated in three dimensions inside a cylinder and projected to
 * the canvas in 3/4 perspective: further-back balls sit higher on screen, are
 * drawn smaller and darker, and everything is painted back-to-front. That
 * depth is what makes a heap of circles read as an actual pit of balls rather
 * than a flat wall of them.
 *
 * Large orders are sampled rather than drawn ball for ball — see
 * allocateRenderedCounts.
 */

const MAX_RENDERED_BALLS = 170;

/** World units are canvas pixels, so gravity is in px/s². */
const GRAVITY = 2100;
const WALL_RESTITUTION = 0.32;
const FLOOR_RESTITUTION = 0.26;
const BALL_RESTITUTION = 0.18;
const AIR_DAMPING = 0.992;
const FLOOR_FRICTION = 0.86;

const FIXED_STEP = 1 / 120;
const MAX_STEPS_PER_FRAME = 8;
const REMOVAL_DURATION = 0.22;
const POUR_DURATION = 1;

/**
 * A dense pile never truly stops under positional correction, so simulation
 * stops this long after the last change instead and the loop idles.
 */
const SETTLE_TIMEOUT = 3.5;

/** How much the vertical axis is squashed to fake perspective. */
const SQUASH = 0.34;

/**
 * How far a full order heaps above the rim, as a multiple of pit depth.
 * Above 1 the pit brims over, which is what makes the balls visible.
 */
const OVERFILL = 1.5;

/** How much nearer balls grow, as a fraction of their radius. */
const DEPTH_SCALE = 0.16;

/** Ink, matching the page's border colour. */
const INK = '#15100b';

/**
 * Loaded ball photos, keyed by URL and shared across instances.
 *
 * @type {Map<string, {element: HTMLImageElement, loaded: boolean, failed: boolean}>}
 */
const imageCache = new Map();

/**
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
 * Share the rendered-ball budget across colours, preserving proportions.
 *
 * Below the budget every ordered ball gets its own; above it the pit is
 * simply full. Largest-remainder rounding keeps the counts summing to the
 * budget, and every ordered colour is guaranteed at least one ball so a small
 * accent never vanishes from the mix.
 *
 * @param {Array<{id: string, quantity: number}>} mix
 * @param {number} budget
 * @returns {Map<string, number>}
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
 * @param {Array<unknown>} items
 */
function shuffle(items) {
    for (let i = items.length - 1; i > 0; i -= 1) {
        const j = Math.floor(Math.random() * (i + 1));
        [items[i], items[j]] = [items[j], items[i]];
    }
}

/**
 * Pre-render a glossy sphere.
 *
 * Sprites are drawn at the largest size a ball can reach and scaled down when
 * painted, so the nearest balls stay crisp.
 *
 * @param {string} color
 * @param {number} radius
 * @param {number} dpr
 * @returns {HTMLCanvasElement}
 */
function createBallSprite(color, radius, dpr) {
    const pad = 3;
    const size = Math.ceil((radius + pad) * 2 * dpr);
    const sprite = document.createElement('canvas');
    sprite.width = size;
    sprite.height = size;

    const ctx = sprite.getContext('2d');
    ctx.scale(dpr, dpr);

    const c = radius + pad;

    // Base colour.
    ctx.beginPath();
    ctx.arc(c, c, radius, 0, Math.PI * 2);
    ctx.fillStyle = color;
    ctx.fill();

    // Form shading: lit from the upper left, shadowed lower right.
    const shade = ctx.createRadialGradient(
        c - radius * 0.38, c - radius * 0.42, radius * 0.08,
        c, c, radius * 1.05,
    );
    shade.addColorStop(0, 'rgba(255,255,255,0.62)');
    shade.addColorStop(0.42, 'rgba(255,255,255,0)');
    shade.addColorStop(0.78, 'rgba(21,16,11,0.1)');
    shade.addColorStop(1, 'rgba(21,16,11,0.42)');
    ctx.fillStyle = shade;
    ctx.fill();

    // Bounced light along the lower-right rim stops it reading as a flat disc.
    ctx.save();
    ctx.beginPath();
    ctx.arc(c, c, radius, 0, Math.PI * 2);
    ctx.clip();
    const bounce = ctx.createRadialGradient(
        c + radius * 0.55, c + radius * 0.6, radius * 0.05,
        c + radius * 0.4, c + radius * 0.45, radius * 0.9,
    );
    bounce.addColorStop(0, 'rgba(255,255,255,0.3)');
    bounce.addColorStop(1, 'rgba(255,255,255,0)');
    ctx.fillStyle = bounce;
    ctx.fill();
    ctx.restore();

    // Outline, matching the page's ink borders.
    ctx.beginPath();
    ctx.arc(c, c, radius - 0.6, 0, Math.PI * 2);
    ctx.strokeStyle = 'rgba(21,16,11,0.5)';
    ctx.lineWidth = Math.max(1, radius * 0.055);
    ctx.stroke();

    // Specular highlight.
    ctx.beginPath();
    ctx.ellipse(
        c - radius * 0.33, c - radius * 0.4,
        radius * 0.25, radius * 0.17,
        -Math.PI / 5, 0, Math.PI * 2,
    );
    ctx.fillStyle = 'rgba(255,255,255,0.9)';
    ctx.fill();

    return sprite;
}

/**
 * Pre-render a photographed ball, clipped to a circle.
 *
 * @param {HTMLImageElement} image
 * @param {number} radius
 * @param {number} dpr
 * @returns {HTMLCanvasElement}
 */
function createPhotoSprite(image, radius, dpr) {
    const pad = 3;
    const size = Math.ceil((radius + pad) * 2 * dpr);
    const sprite = document.createElement('canvas');
    sprite.width = size;
    sprite.height = size;

    const ctx = sprite.getContext('2d');
    ctx.scale(dpr, dpr);

    const c = radius + pad;

    ctx.save();
    ctx.beginPath();
    ctx.arc(c, c, radius, 0, Math.PI * 2);
    ctx.clip();

    const natural = Math.min(image.naturalWidth || 1, image.naturalHeight || 1);
    const scale = (radius * 2) / natural;
    const w = (image.naturalWidth || 1) * scale;
    const h = (image.naturalHeight || 1) * scale;

    ctx.drawImage(image, c - w / 2, c - h / 2, w, h);

    // Match the generated balls' shading so a mixed pit reads as one object.
    const shade = ctx.createRadialGradient(
        c - radius * 0.38, c - radius * 0.42, radius * 0.08,
        c, c, radius * 1.05,
    );
    shade.addColorStop(0, 'rgba(255,255,255,0.3)');
    shade.addColorStop(0.5, 'rgba(255,255,255,0)');
    shade.addColorStop(1, 'rgba(21,16,11,0.4)');
    ctx.fillStyle = shade;
    ctx.fill();
    ctx.restore();

    ctx.beginPath();
    ctx.arc(c, c, radius - 0.6, 0, Math.PI * 2);
    ctx.strokeStyle = 'rgba(21,16,11,0.5)';
    ctx.lineWidth = Math.max(1, radius * 0.055);
    ctx.stroke();

    return sprite;
}

/**
 * Mount the ball pit onto a canvas.
 *
 * @param {HTMLCanvasElement} canvas
 * @param {{onStats?: (stats: {scale: number, rendered: number, quantity: number}) => void}} [options]
 */
export function createBallPit(canvas, options = {}) {
    const ctx = canvas.getContext('2d');
    const onStats = options.onStats ?? (() => {});
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /**
     * Balls live in world space: x and z span the circular floor, y is height
     * above it.
     *
     * @type {Array<{x: number, y: number, z: number, vx: number, vy: number, vz: number,
     *               color: string, styleKey: string, typeId: string,
     *               spawnAt: number, removingAt: number|null}>}
     */
    let balls = [];
    /** @type {Map<string, HTMLCanvasElement>} */
    let sprites = new Map();
    /** @type {Array<{id: string, color: string, image?: string|null, quantity: number}>} */
    let currentMix = [];

    let width = 0;
    let height = 0;
    let dpr = 1;
    let radius = 14;
    let pit = null;
    let clock = 0;
    let spawnCursor = 0;
    let lastChangeAt = 0;
    let frame = null;
    let lastTimestamp = null;

    function measure() {
        const rect = canvas.getBoundingClientRect();
        width = Math.max(rect.width, 1);
        height = Math.max(rect.height, 1);
        dpr = Math.min(window.devicePixelRatio || 1, 2);

        canvas.width = Math.round(width * dpr);
        canvas.height = Math.round(height * dpr);
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

        const floorRadius = width * 0.42;

        pit = {
            centerX: width / 2,
            // Screen y of the floor's centre, and the pit's internal height.
            //
            // Deliberately shallow and wide. A deep bucket hides its contents:
            // in this projection you see into the pit through the mouth, so
            // anything resting well below the rim is behind the near wall.
            floorY: height * 0.70,
            depth: height * 0.2,
            radius: floorRadius,
            // The rim flares out very slightly, like a real tub.
            rimRadius: floorRadius * 1.04,
        };

        pit.rimY = pit.floorY - pit.depth;

        // Size the balls so a full budget heaps *above* the rim rather than
        // settling out of sight. Derived from the pit's own dimensions so it
        // holds at any canvas size.
        const fillHeight = pit.depth * OVERFILL;
        const packing = 0.62;
        const cubed = (pit.radius ** 2 * fillHeight * packing * 3) / (4 * MAX_RENDERED_BALLS);

        radius = Math.min(width * 0.085, Math.max(width * 0.035, Math.cbrt(cubed)));

        rebuildSprites();
        lastChangeAt = clock;
    }

    /**
     * Project a ball from world space to the canvas.
     *
     * @returns {{sx: number, sy: number, scale: number, depth: number}}
     */
    function project(ball) {
        // depth: 0 at the back of the pit, 1 at the front.
        const depth = (ball.z + pit.radius) / (pit.radius * 2);

        return {
            sx: pit.centerX + ball.x,
            sy: pit.floorY + ball.z * SQUASH - ball.y,
            scale: 1 + (ball.z / pit.radius) * DEPTH_SCALE,
            depth,
        };
    }

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

            // Sprites are rendered at the largest on-screen size a ball can
            // take, so the nearest balls never look soft.
            const spriteRadius = radius * (1 + DEPTH_SCALE);

            if (entry.image) {
                const cached = loadImage(entry.image, () => {
                    rebuildSprites();
                    ensureRunning();
                });

                if (cached.loaded) {
                    sprites.set(key, createPhotoSprite(cached.element, spriteRadius, dpr));

                    continue;
                }
            }

            sprites.set(key, createBallSprite(entry.color, spriteRadius, dpr));
        }
    }

    /**
     * Reconcile the balls on screen with the requested mix.
     *
     * @param {Array<{id: string, color: string, image?: string|null, quantity: number}>} mix
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

        // A ball type may be recoloured or photographed between renders.
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

        for (const [typeId, group] of liveByType) {
            if (!targets.has(typeId)) {
                group.forEach(retire);
            }
        }

        const toSpawn = [];

        for (const [typeId, target] of targets) {
            const group = liveByType.get(typeId) ?? [];
            const delta = target - group.length;

            if (delta > 0) {
                for (let i = 0; i < delta; i += 1) {
                    toSpawn.push(typeId);
                }
            } else if (delta < 0) {
                // Take from the top of the pile so it deflates downward.
                group
                    .slice()
                    .sort((a, b) => b.y - a.y)
                    .slice(0, -delta)
                    .forEach(retire);
            }
        }

        if (toSpawn.length > 0) {
            // Interleave so colours pour in blended, not in solid layers.
            shuffle(toSpawn);

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
        const spawnAt = Math.max(clock, spawnCursor + interval);
        spawnCursor = spawnAt;

        // Drop in from a random point over the mouth of the pit.
        const angle = Math.random() * Math.PI * 2;
        const reach = Math.sqrt(Math.random()) * (pit.radius - radius) * 0.75;

        balls.push({
            typeId,
            color: style.color,
            styleKey: style.styleKey,
            x: Math.cos(angle) * reach,
            z: Math.sin(angle) * reach,
            y: pit.depth + radius * 2 + Math.random() * height * 0.3,
            vx: (Math.random() * 2 - 1) * 25,
            vy: -40 - Math.random() * 40,
            vz: (Math.random() * 2 - 1) * 25,
            spawnAt,
            removingAt: null,
        });
    }

    /** Settle the pile before the first paint, for reduced-motion viewers. */
    function settleInstantly() {
        for (const ball of balls) {
            ball.spawnAt = 0;

            if (ball.y > pit.depth) {
                ball.y = pit.depth * 0.5 + Math.random() * pit.depth * 0.4;
                ball.vy = 0;
            }
        }

        spawnCursor = clock;

        for (let i = 0; i < 170; i += 1) {
            step(FIXED_STEP);
        }
    }

    function step(dt) {
        clock += dt;

        if (balls.some((b) => b.removingAt !== null && clock - b.removingAt >= REMOVAL_DURATION)) {
            balls = balls.filter((b) => b.removingAt === null || clock - b.removingAt < REMOVAL_DURATION);
        }

        const active = balls.filter((ball) => ball.spawnAt <= clock);

        for (const ball of active) {
            ball.vy -= GRAVITY * dt;
            ball.vx *= AIR_DAMPING;
            ball.vy *= AIR_DAMPING;
            ball.vz *= AIR_DAMPING;
            ball.x += ball.vx * dt;
            ball.y += ball.vy * dt;
            ball.z += ball.vz * dt;
        }

        resolveCollisions(active);

        for (const ball of active) {
            constrain(ball);
        }
    }

    function constrain(ball) {
        // Floor.
        if (ball.y < radius) {
            ball.y = radius;

            if (ball.vy < 0) {
                ball.vy = -ball.vy * FLOOR_RESTITUTION;
            }

            ball.vx *= FLOOR_FRICTION;
            ball.vz *= FLOOR_FRICTION;
        }

        // Cylinder wall. Applied at every height: balls heap above the rim,
        // and without this they roll off the crown and snap back in when they
        // drop past it.
        const distance = Math.hypot(ball.x, ball.z);
        const limit = pit.radius - radius;

        if (distance > limit && distance > 0) {
            const nx = ball.x / distance;
            const nz = ball.z / distance;

            ball.x = nx * limit;
            ball.z = nz * limit;

            const radial = ball.vx * nx + ball.vz * nz;

            if (radial > 0) {
                ball.vx -= radial * nx * (1 + WALL_RESTITUTION);
                ball.vz -= radial * nz * (1 + WALL_RESTITUTION);
            }
        }
    }

    function resolveCollisions(active) {
        if (active.length < 2) {
            return;
        }

        const cell = radius * 2;
        const grid = new Map();
        const key = (a, b, c) => `${a}:${b}:${c}`;

        for (const ball of active) {
            const k = key(
                Math.floor(ball.x / cell),
                Math.floor(ball.y / cell),
                Math.floor(ball.z / cell),
            );

            if (!grid.has(k)) {
                grid.set(k, []);
            }

            grid.get(k).push(ball);
        }

        const minimum = radius * 2;

        for (const ball of active) {
            const cx = Math.floor(ball.x / cell);
            const cy = Math.floor(ball.y / cell);
            const cz = Math.floor(ball.z / cell);

            for (let ox = -1; ox <= 1; ox += 1) {
                for (let oy = -1; oy <= 1; oy += 1) {
                    for (let oz = -1; oz <= 1; oz += 1) {
                        const bucket = grid.get(key(cx + ox, cy + oy, cz + oz));

                        if (!bucket) {
                            continue;
                        }

                        for (const other of bucket) {
                            if (other === ball) {
                                continue;
                            }

                            let dx = other.x - ball.x;
                            let dy = other.y - ball.y;
                            let dz = other.z - ball.z;
                            let distance = Math.hypot(dx, dy, dz);

                            if (distance >= minimum) {
                                continue;
                            }

                            if (distance === 0) {
                                dx = Math.random() - 0.5;
                                dy = Math.random() - 0.5;
                                dz = Math.random() - 0.5;
                                distance = Math.hypot(dx, dy, dz) || 1;
                            }

                            const nx = dx / distance;
                            const ny = dy / distance;
                            const nz = dz / distance;
                            const overlap = (minimum - distance) / 2;

                            ball.x -= nx * overlap;
                            ball.y -= ny * overlap;
                            ball.z -= nz * overlap;
                            other.x += nx * overlap;
                            other.y += ny * overlap;
                            other.z += nz * overlap;

                            const relative =
                                (other.vx - ball.vx) * nx +
                                (other.vy - ball.vy) * ny +
                                (other.vz - ball.vz) * nz;

                            if (relative < 0) {
                                const impulse = relative * (1 + BALL_RESTITUTION) * 0.5;
                                ball.vx += impulse * nx;
                                ball.vy += impulse * ny;
                                ball.vz += impulse * nz;
                                other.vx -= impulse * nx;
                                other.vy -= impulse * ny;
                                other.vz -= impulse * nz;
                            }
                        }
                    }
                }
            }
        }
    }

    /* Drawing ----------------------------------------------------------- */

    function draw() {
        ctx.clearRect(0, 0, width, height);

        drawPitInterior();
        drawBalls();
        drawPitFront();
    }

    /** The inside of the pit: back wall, floor and the far half of the rim. */
    function drawPitInterior() {
        const { centerX, floorY, rimY, radius: r, rimRadius } = pit;

        // Outer body, so the pit reads as a solid object on the page.
        ctx.beginPath();
        ctx.moveTo(centerX - rimRadius, rimY);
        ctx.lineTo(centerX - r, floorY);
        ctx.ellipse(centerX, floorY, r, r * SQUASH, 0, Math.PI, 0, true);
        ctx.lineTo(centerX + rimRadius, rimY);
        ctx.closePath();

        const body = ctx.createLinearGradient(centerX - rimRadius, 0, centerX + rimRadius, 0);
        body.addColorStop(0, '#f0e2cc');
        body.addColorStop(0.35, '#fffbf4');
        body.addColorStop(1, '#e6d4b8');
        ctx.fillStyle = body;
        ctx.fill();

        // Inside face, darker than the outside so the mouth reads as open.
        ctx.beginPath();
        ctx.ellipse(centerX, rimY, rimRadius, rimRadius * SQUASH, 0, 0, Math.PI * 2);
        ctx.fillStyle = '#efe0c7';
        ctx.fill();

        // Shadow gathering at the bottom of the inside wall.
        const well = ctx.createLinearGradient(0, rimY, 0, floorY);
        well.addColorStop(0, 'rgba(21,16,11,0.04)');
        well.addColorStop(1, 'rgba(21,16,11,0.22)');
        ctx.fillStyle = well;
        ctx.fill();

        // The far half of the rim, behind the balls. Stroking the whole
        // ellipse after them would draw this line straight across the pile.
        ctx.beginPath();
        ctx.ellipse(centerX, rimY, rimRadius, rimRadius * SQUASH, 0, Math.PI, 0);
        ctx.strokeStyle = INK;
        ctx.lineWidth = 3;
        ctx.stroke();
    }

    function drawBalls() {
        // Back to front, so nearer balls overlap the ones behind them.
        const visible = balls
            .filter((ball) => ball.spawnAt <= clock && sprites.has(ball.styleKey))
            .sort((a, b) => a.z - b.z);

        for (const ball of visible) {
            const { sx, sy, scale, depth } = project(ball);
            const sprite = sprites.get(ball.styleKey);

            let shrink = 1;

            if (ball.removingAt !== null) {
                shrink = Math.max(1 - (clock - ball.removingAt) / REMOVAL_DURATION, 0);
            }

            const drawRadius = radius * (1 + DEPTH_SCALE) * scale * shrink;

            ctx.drawImage(sprite, sx - drawRadius, sy - drawRadius, drawRadius * 2, drawRadius * 2);

            // Depth haze: balls at the back sit in shadow.
            const haze = (1 - depth) * 0.34;

            if (haze > 0.02) {
                ctx.save();
                ctx.globalAlpha = haze;
                ctx.beginPath();
                ctx.arc(sx, sy, drawRadius * 0.98, 0, Math.PI * 2);
                ctx.fillStyle = '#1b2a4a';
                ctx.fill();
                ctx.restore();
            }
        }
    }

    /** The near half of the rim, painted over the balls so they sit inside. */
    function drawPitFront() {
        const { centerX, floorY, rimY, radius: r, rimRadius } = pit;

        // The near wall only, drawn over the balls. Both arcs must sweep the
        // FRONT (lower) half of their ellipse: canvas angles increase
        // clockwise on screen, so PI -> 0 needs counterclockwise=true or it
        // takes the back half and the fill swallows the whole pit.
        ctx.beginPath();
        ctx.moveTo(centerX - rimRadius, rimY);
        ctx.ellipse(centerX, rimY, rimRadius, rimRadius * SQUASH, 0, Math.PI, 0, true);
        ctx.lineTo(centerX + r, floorY);
        ctx.ellipse(centerX, floorY, r, r * SQUASH, 0, 0, Math.PI);
        ctx.closePath();

        const front = ctx.createLinearGradient(centerX - rimRadius, 0, centerX + rimRadius, 0);
        front.addColorStop(0, '#f4e7d3');
        front.addColorStop(0.3, '#fffdf8');
        front.addColorStop(1, '#eadcc2');
        ctx.fillStyle = front;
        ctx.fill();

        ctx.strokeStyle = INK;
        ctx.lineWidth = 3;
        ctx.lineJoin = 'round';
        ctx.stroke();

        // Near half of the rim lip, over the balls.
        ctx.beginPath();
        ctx.ellipse(centerX, rimY, rimRadius, rimRadius * SQUASH, 0, Math.PI, 0, true);
        ctx.strokeStyle = INK;
        ctx.lineWidth = 3;
        ctx.stroke();

        // Outer silhouette.
        ctx.beginPath();
        ctx.moveTo(centerX - rimRadius, rimY);
        ctx.lineTo(centerX - r, floorY);
        ctx.ellipse(centerX, floorY, r, r * SQUASH, 0, Math.PI, 0, true);
        ctx.lineTo(centerX + rimRadius, rimY);
        ctx.strokeStyle = INK;
        ctx.lineWidth = 3;
        ctx.stroke();
    }

    /* Loop -------------------------------------------------------------- */

    function isAtRest() {
        if (balls.some((ball) => ball.removingAt !== null || ball.spawnAt > clock)) {
            return false;
        }

        if (balls.every((b) => Math.abs(b.vx) < 1.5 && Math.abs(b.vy) < 1.5 && Math.abs(b.vz) < 1.5)) {
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
