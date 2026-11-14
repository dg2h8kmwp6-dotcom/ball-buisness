/**
 * Headless tests for the animated ball bag (resources/js/ball-bag.js).
 *
 * The bag is a canvas animation, so it is exercised here by stubbing just
 * enough of the canvas API to run the real module under Node, driving
 * requestAnimationFrame manually, and inspecting the drawImage calls to see
 * where the balls ended up and in what colours.
 *
 * Run with: npm run test:bag
 */

const WIDTH = 420;
const HEIGHT = 420;

/** Every sprite canvas, mapped to the colour it was painted with. */
const spriteColors = new Map();

function makeGradient() {
    return { addColorStop() {} };
}

function makeContext(owner) {
    return {
        _owner: owner,
        set fillStyle(value) {
            // createBallSprite sets the raw colour string first.
            if (typeof value === 'string' && !spriteColors.has(owner)) {
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
        createRadialGradient: makeGradient,
        createLinearGradient: makeGradient,
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

globalThis.document = {
    createElement: () => makeCanvas(32, 32),
};

globalThis.window = {
    devicePixelRatio: 2,
    matchMedia: () => ({ matches: false }),
};

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

/** Drive requestAnimationFrame manually so we control the simulated time. */
let pending = [];
let now = 0;
globalThis.requestAnimationFrame = (cb) => {
    pending.push(cb);
    return pending.length;
};
globalThis.cancelAnimationFrame = () => {};

let framesRun = 0;

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
        framesRun += 1;
        due.forEach((cb) => cb(now));
    }
}

const { createBallBag } = await import(
    new URL('../../resources/js/ball-bag.js', import.meta.url).href
);

const canvas = makeCanvas(WIDTH, HEIGHT);
let lastStats = null;
const widget = createBallBag(canvas, { onStats: (stats) => (lastStats = stats) });

// Bag geometry, mirroring the module's own layout maths.
const bag = {
    centerX: WIDTH / 2,
    topY: HEIGHT * 0.26,
    bottomY: HEIGHT * 0.94,
    halfTop: WIDTH * 0.37,
    halfBottom: WIDTH * 0.305,
};

function halfWidthAt(y) {
    const t = Math.min(Math.max((bag.bottomY - y) / (bag.bottomY - bag.topY), 0), 1);
    return bag.halfBottom + (bag.halfTop - bag.halfBottom) * t;
}

function settleAndRead(mix, seconds = 12) {
    canvas._draws = [];
    framesRun = 0;
    widget.setMix(mix);
    advance(seconds);

    return { draws: canvas._draws, stats: lastStats, frames: framesRun };
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

console.log('\n2. Balls settle inside the bag');
{
    const { draws } = settleAndRead([{ id: '1', color: '#e8392e', quantity: 120 }]);

    const escapedFloor = draws.filter((d) => d.cy - d.r > bag.bottomY + 1);
    const escapedWall = draws.filter(
        (d) => d.cy > bag.topY && Math.abs(d.cx - bag.centerX) - d.r > halfWidthAt(d.cy) + 1,
    );
    const stillFalling = draws.filter((d) => d.cy + d.r < bag.topY);

    check('none below the bag floor', escapedFloor.length === 0, `${escapedFloor.length} escaped`);
    check('none through the walls', escapedWall.length === 0, `${escapedWall.length} escaped`);
    check('none still in mid-air', stillFalling.length === 0, `${stillFalling.length} airborne`);
}

console.log('\n3. Large order — balls are sampled, proportions preserved');
{
    const { draws, stats } = settleAndRead([
        { id: '1', color: '#e8392e', quantity: 15000 },
        { id: '2', color: '#2b7fd4', quantity: 5000 },
    ]);

    check('rendered count within budget', draws.length <= 180, `drew ${draws.length}`);
    check('scale is > 1', stats.scale > 1, `got ${stats.scale.toFixed(1)}`);

    const red = draws.filter((d) => d.color === '#e8392e').length;
    const blue = draws.filter((d) => d.color === '#2b7fd4').length;
    const redShare = red / (red + blue);

    check(
        'red is ~75% of the drawn balls',
        Math.abs(redShare - 0.75) < 0.05,
        `got ${(redShare * 100).toFixed(1)}%`,
    );
}

console.log('\n4. A tiny colour alongside a huge one still appears');
{
    const { draws } = settleAndRead([
        { id: '1', color: '#e8392e', quantity: 20000 },
        { id: '3', color: '#3fc0b0', quantity: 50 },
    ]);

    const teal = draws.filter((d) => d.color === '#3fc0b0').length;
    check('the 50-ball colour is visible', teal >= 1, `drew ${teal}`);
}

console.log('\n5. Fill level tracks quantity up to a full bag');
{
    const heights = [];
    const bagHeight = bag.bottomY - bag.topY;

    for (const quantity of [40, 100, 170, 5000]) {
        widget.setMix([]);
        advance(1.5);
        const { draws } = settleAndRead([{ id: '1', color: '#e8392e', quantity }]);
        const topOfPile = Math.min(...draws.map((d) => d.cy));
        heights.push({ quantity, fill: bag.bottomY - topOfPile, drawn: draws.length });
    }

    console.log(
        '        ' +
            heights
                .map(
                    (h) =>
                        `${h.quantity}→${h.fill.toFixed(0)}px/${((h.fill / bagHeight) * 100).toFixed(0)}% (${h.drawn} drawn)`,
                )
                .join('  '),
    );

    check(
        'the pile deepens as the order grows, below the cap',
        heights[0].fill < heights[1].fill && heights[1].fill < heights[2].fill,
        JSON.stringify(heights.map((h) => Math.round(h.fill))),
    );
    check(
        'a 170-ball order is >1.8x the depth of a 40-ball order',
        heights[2].fill > heights[0].fill * 1.8,
        `${heights[0].fill.toFixed(0)}px vs ${heights[2].fill.toFixed(0)}px`,
    );
    check(
        'past the cap the bag stays full rather than growing',
        Math.abs(heights[3].fill - heights[2].fill) < 12,
        `${heights[2].fill.toFixed(0)}px vs ${heights[3].fill.toFixed(0)}px`,
    );
    check(
        'a maxed-out order looks properly full (>70% of the bag)',
        heights[3].fill / bagHeight > 0.7,
        `${((heights[3].fill / bagHeight) * 100).toFixed(0)}% full`,
    );
}

console.log('\n6. Removing a colour removes its balls');
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

console.log('\n7. Emptying the bag clears it');
{
    const { draws, stats } = settleAndRead([]);
    check('nothing drawn', draws.length === 0, `drew ${draws.length}`);
    check('quantity reported as 0', stats.quantity === 0, `got ${stats.quantity}`);
}

console.log('\n8. Recolouring a ball type updates the balls already in the bag');
{
    settleAndRead([{ id: '9', color: '#e8392e', quantity: 80 }]);
    const { draws } = settleAndRead([{ id: '9', color: '#8257c9', quantity: 80 }]);

    const purple = draws.filter((d) => d.color === '#8257c9').length;
    const red = draws.filter((d) => d.color === '#e8392e').length;

    check('all balls are now purple', purple === draws.length && red === 0, `${purple} purple, ${red} red`);
}

console.log('\n9. Proportions hold across an awkward three-way mix');
{
    const { draws } = settleAndRead([
        { id: 'a', color: '#e8392e', quantity: 3333 },
        { id: 'b', color: '#2b7fd4', quantity: 3333 },
        { id: 'c', color: '#f6c62b', quantity: 3334 },
    ]);

    const counts = ['#e8392e', '#2b7fd4', '#f6c62b'].map(
        (c) => draws.filter((d) => d.color === c).length,
    );

    check('budget fully used', draws.length === 170, `drew ${draws.length}`);
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
