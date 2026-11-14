/**
 * Headless tests for the animated ball pit (resources/js/ball-pit.js).
 *
 * The pit is a canvas animation, so it is exercised here by stubbing enough
 * of the canvas API to run the real module under Node, driving
 * requestAnimationFrame by hand, and inspecting the drawImage calls to see
 * where the balls ended up, how big they were drawn, and in what colours.
 *
 * Run with: npm run test:pit
 */

const WIDTH = 420;
const HEIGHT = 420;

/* Mirrors of the module's own layout constants, so the expectations below
 * are written in the same terms the module draws in. */
const SQUASH = 0.34;
const DEPTH_SCALE = 0.16;
const OVERFILL = 1.5;
const BUDGET = 170;

const PIT = {
    centerX: WIDTH / 2,
    floorY: HEIGHT * 0.7,
    depth: HEIGHT * 0.2,
    radius: WIDTH * 0.42,
};
PIT.rimRadius = PIT.radius * 1.04;
PIT.rimY = PIT.floorY - PIT.depth;

const BALL_RADIUS = Math.min(
    WIDTH * 0.085,
    Math.max(
        WIDTH * 0.035,
        Math.cbrt((PIT.radius ** 2 * PIT.depth * OVERFILL * 0.62 * 3) / (4 * BUDGET)),
    ),
);

/** Every sprite canvas, mapped to the colour it was painted with. */
const spriteColors = new Map();

const gradient = () => ({ addColorStop() {} });

function makeContext(owner) {
    return {
        set fillStyle(value) {
            // createBallSprite sets the raw colour string first.
            if (typeof value === 'string' && value.startsWith('#') && !spriteColors.has(owner)) {
                spriteColors.set(owner, value);
            }
        },
        get fillStyle() {
            return '';
        },
        strokeStyle: '',
        lineWidth: 1,
        lineJoin: '',
        lineCap: '',
        globalAlpha: 1,
        scale() {},
        setTransform() {},
        clearRect() {},
        save() {},
        restore() {},
        beginPath() {},
        arc() {},
        ellipse() {},
        moveTo() {},
        lineTo() {},
        quadraticCurveTo() {},
        closePath() {},
        rect() {},
        fill() {},
        stroke() {},
        clip() {},
        createRadialGradient: gradient,
        createLinearGradient: gradient,
        drawImage(sprite, x, y, w) {
            owner._draws.push({
                color: spriteColors.get(sprite) ?? null,
                cx: x + w / 2,
                cy: y + w / 2,
                r: w / 2,
            });
        },
    };
}

function makeCanvas(width, height) {
    const canvas = {
        width,
        height,
        _draws: [],
        getBoundingClientRect: () => ({ width, height, top: 0, left: 0 }),
    };

    canvas.getContext = () => (canvas._ctx ??= makeContext(canvas));

    return canvas;
}

globalThis.document = { createElement: () => makeCanvas(64, 64) };
globalThis.window = { devicePixelRatio: 2, matchMedia: () => ({ matches: false }) };
globalThis.ResizeObserver = class {
    observe() {}
    disconnect() {}
};
globalThis.Path2D = class {
    moveTo() {}
    lineTo() {}
    quadraticCurveTo() {}
    closePath() {}
    rect() {}
    addPath() {}
};

/** Drive requestAnimationFrame manually so we control simulated time. */
let pending = [];
let now = 0;
globalThis.requestAnimationFrame = (cb) => pending.push(cb);
globalThis.cancelAnimationFrame = () => {};

const { createBallPit } = await import(new URL('../../resources/js/ball-pit.js', import.meta.url).href);

const canvas = makeCanvas(WIDTH, HEIGHT);
let lastStats = null;
const pit = createBallPit(canvas, { onStats: (stats) => (lastStats = stats) });

function advance(seconds) {
    const frames = Math.round(seconds / (1 / 60));

    for (let i = 0; i < frames; i += 1) {
        now += 1000 / 60;
        const due = pending;
        pending = [];

        if (due.length === 0) {
            continue;
        }

        // Keep only the most recent frame's draw calls.
        canvas._draws = [];
        due.forEach((cb) => cb(now));
    }
}

function settleAndRead(mix, seconds = 12) {
    canvas._draws = [];
    pit.setMix(mix);
    advance(seconds);

    return { draws: canvas._draws, stats: lastStats };
}

const failures = [];

function check(name, condition, detail = '') {
    if (condition) {
        console.log(`  PASS  ${name}`);
    } else {
        console.log(`  FAIL  ${name} ${detail}`);
        failures.push(name);
    }
}

console.log('\n1. Small order — every ball is shown individually');
{
    const { draws, stats } = settleAndRead([
        { id: '1', color: '#e8392e', quantity: 40 },
        { id: '2', color: '#2b7fd4', quantity: 20 },
    ]);

    check('scale is 1:1', stats.scale === 1, `got ${stats.scale}`);
    check('60 balls drawn', draws.length === 60, `got ${draws.length}`);

    const red = draws.filter((d) => d.color === '#e8392e').length;
    const blue = draws.filter((d) => d.color === '#2b7fd4').length;
    check('40 red / 20 blue', red === 40 && blue === 20, `got ${red}/${blue}`);
}

console.log('\n2. Balls settle inside the pit');
{
    const { draws } = settleAndRead([{ id: '1', color: '#e8392e', quantity: 120 }]);

    // The floor is an ellipse, so its lowest point sits SQUASH*radius below centre.
    const floorLimit = PIT.floorY + PIT.radius * SQUASH + BALL_RADIUS * 1.4;
    const sideLimit = PIT.rimRadius + BALL_RADIUS * 1.4;

    // The pit brims over by design, so balls above the rim are expected;
    // what must not happen is one left hanging well clear of the heap.
    const ceiling = PIT.floorY - PIT.depth * OVERFILL - BALL_RADIUS * 4;

    const throughFloor = draws.filter((d) => d.cy > floorLimit);
    const throughWall = draws.filter((d) => Math.abs(d.cx - PIT.centerX) > sideLimit);
    const airborne = draws.filter((d) => d.cy < ceiling);

    check('none below the floor', throughFloor.length === 0, `${throughFloor.length} escaped`);
    check('none through the walls', throughWall.length === 0, `${throughWall.length} escaped`);
    check('none left floating above the heap', airborne.length === 0, `${airborne.length} airborne`);
}

console.log('\n3. Depth: balls are painted back to front');
{
    const { draws } = settleAndRead([{ id: '1', color: '#e8392e', quantity: 140 }]);

    // Drawn radius grows with depth, so a correctly sorted frame paints radii
    // in non-decreasing order. This is what stops the pile looking flat.
    let inversions = 0;
    for (let i = 1; i < draws.length; i += 1) {
        if (draws[i].r < draws[i - 1].r - 0.001) {
            inversions += 1;
        }
    }

    const radii = draws.map((d) => d.r);
    const spread = Math.max(...radii) / Math.min(...radii);

    check('painted back to front', inversions === 0, `${inversions} out-of-order draws`);
    check(
        'near balls are drawn larger than far ones',
        spread > 1.15,
        `near/far size ratio ${spread.toFixed(3)}`,
    );
    check(
        'depth scaling stays within its budget',
        spread <= (1 + DEPTH_SCALE) / (1 - DEPTH_SCALE) + 0.01,
        `ratio ${spread.toFixed(3)}`,
    );
}

console.log('\n4. Large order — balls are sampled, proportions preserved');
{
    const { draws, stats } = settleAndRead([
        { id: '1', color: '#e8392e', quantity: 15000 },
        { id: '2', color: '#2b7fd4', quantity: 5000 },
    ]);

    check('rendered count within budget', draws.length <= BUDGET + 10, `drew ${draws.length}`);
    check('scale is > 1', stats.scale > 1, `got ${stats.scale.toFixed(1)}`);

    const red = draws.filter((d) => d.color === '#e8392e').length;
    const blue = draws.filter((d) => d.color === '#2b7fd4').length;
    const share = red / (red + blue);

    check('red is ~75% of the drawn balls', Math.abs(share - 0.75) < 0.05, `got ${(share * 100).toFixed(1)}%`);
}

console.log('\n5. A tiny colour alongside a huge one still appears');
{
    const { draws } = settleAndRead([
        { id: '1', color: '#e8392e', quantity: 20000 },
        { id: '3', color: '#3fc0b0', quantity: 50 },
    ]);

    const teal = draws.filter((d) => d.color === '#3fc0b0').length;
    check('the 50-ball colour is visible', teal >= 1, `drew ${teal}`);
}

console.log('\n6. Fill level tracks quantity up to a full pit');
{
    const heights = [];

    for (const quantity of [40, 100, 170, 5000]) {
        pit.setMix([]);
        advance(1.5);
        const { draws } = settleAndRead([{ id: '1', color: '#e8392e', quantity }]);
        const topOfPile = Math.min(...draws.map((d) => d.cy));
        heights.push({ quantity, fill: PIT.floorY - topOfPile, drawn: draws.length });
    }

    console.log(
        '        ' +
            heights
                .map((h) => `${h.quantity}→${h.fill.toFixed(0)}px/${((h.fill / PIT.depth) * 100).toFixed(0)}% (${h.drawn})`)
                .join('  '),
    );

    check(
        'the pile deepens as the order grows',
        heights[0].fill < heights[1].fill && heights[1].fill < heights[2].fill,
        JSON.stringify(heights.map((h) => Math.round(h.fill))),
    );
    check(
        'past the cap the pit stays full rather than growing',
        Math.abs(heights[3].fill - heights[2].fill) < 18,
        `${heights[2].fill.toFixed(0)}px vs ${heights[3].fill.toFixed(0)}px`,
    );
    check(
        'a full order heaps above the rim, where it can be seen',
        heights[3].fill > PIT.depth * 1.05,
        `${((heights[3].fill / PIT.depth) * 100).toFixed(0)}% of pit depth`,
    );
    check(
        'but does not tower over it',
        heights[3].fill < PIT.depth * (OVERFILL + 0.6),
        `${((heights[3].fill / PIT.depth) * 100).toFixed(0)}% of pit depth`,
    );
}

console.log('\n7. Removing a colour removes its balls');
{
    settleAndRead([
        { id: '1', color: '#e8392e', quantity: 300 },
        { id: '2', color: '#2b7fd4', quantity: 300 },
    ]);

    const { draws } = settleAndRead([{ id: '1', color: '#e8392e', quantity: 300 }]);
    const blue = draws.filter((d) => d.color === '#2b7fd4').length;

    check('blue is gone', blue === 0, `${blue} blue remain`);
    check('red remains', draws.length > 0, `drew ${draws.length}`);
}

console.log('\n8. Emptying the pit clears it');
{
    const { draws, stats } = settleAndRead([]);
    check('nothing drawn', draws.length === 0, `drew ${draws.length}`);
    check('quantity reported as 0', stats.quantity === 0, `got ${stats.quantity}`);
}

console.log('\n9. Recolouring a ball type updates the balls already in the pit');
{
    settleAndRead([{ id: '9', color: '#e8392e', quantity: 80 }]);
    const { draws } = settleAndRead([{ id: '9', color: '#8257c9', quantity: 80 }]);

    const purple = draws.filter((d) => d.color === '#8257c9').length;
    const red = draws.filter((d) => d.color === '#e8392e').length;

    check('all balls are now purple', purple === draws.length && red === 0, `${purple} purple, ${red} red`);
}

console.log('\n10. Proportions hold across an awkward three-way mix');
{
    const { draws } = settleAndRead([
        { id: 'a', color: '#e8392e', quantity: 3333 },
        { id: 'b', color: '#2b7fd4', quantity: 3333 },
        { id: 'c', color: '#f6c62b', quantity: 3334 },
    ]);

    const counts = ['#e8392e', '#2b7fd4', '#f6c62b'].map(
        (c) => draws.filter((d) => d.color === c).length,
    );

    check('budget fully used', draws.length === BUDGET, `drew ${draws.length}`);
    check(
        'all three colours within one ball of even',
        Math.max(...counts) - Math.min(...counts) <= 1,
        JSON.stringify(counts),
    );
}

console.log(
    failures.length === 0
        ? '\nAll checks passed.\n'
        : `\n${failures.length} check(s) failed: ${failures.join(', ')}\n`,
);

process.exit(failures.length === 0 ? 0 : 1);
