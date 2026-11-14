import { createBallPit } from './ball-pit.js';

/**
 * Round to two significant figures, so the caption reads "about 120 balls"
 * rather than "about 117.94 balls".
 *
 * @param {number} value
 * @returns {number}
 */
function roundToTwoSignificantFigures(value) {
    if (value <= 0) {
        return 0;
    }

    const magnitude = 10 ** (Math.floor(Math.log10(value)) - 1);

    return Math.round(value / magnitude) * magnitude;
}

/**
 * Wires the quantity controls on the order page to the running totals and the
 * animated bag.
 */
export function initOrderBuilder(root) {
    const rows = Array.from(root.querySelectorAll('[data-ball-row]'));
    const canvas = root.querySelector('[data-ball-pit]');

    if (rows.length === 0 || !canvas) {
        return;
    }

    const currency = root.dataset.currency ?? 'usd';
    const shippingCents = Number(root.dataset.shippingCents ?? 0);

    const money = new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: currency.toUpperCase(),
    });
    const count = new Intl.NumberFormat();

    const totalBallsEl = root.querySelector('[data-total-balls]');
    const subtotalEl = root.querySelector('[data-subtotal]');
    const shippingEl = root.querySelector('[data-shipping]');
    const totalEl = root.querySelector('[data-total]');
    const scaleNoteEl = root.querySelector('[data-scale-note]');
    const legendEl = root.querySelector('[data-legend]');
    const emptyStateEl = root.querySelector('[data-pit-empty]');
    const submitEl = root.querySelector('[data-submit]');

    const pit = createBallPit(canvas, {
        onStats({ scale, quantity }) {
            if (!scaleNoteEl) {
                return;
            }

            if (quantity === 0) {
                scaleNoteEl.textContent = '';

                return;
            }

            scaleNoteEl.textContent =
                scale <= 1.01
                    ? 'Showing every ball in your order.'
                    : `Each ball shown stands for about ${count.format(roundToTwoSignificantFigures(scale))} balls.`;
        },
    });

    /**
     * Read the current state of a ball type row.
     */
    function readRow(row) {
        const input = row.querySelector('[data-qty-input]');
        const step = Number(row.dataset.step || 100);
        const max = Number(input.max || 0);
        const quantity = Math.max(0, Math.min(Number(input.value) || 0, max || Infinity));

        return {
            row,
            input,
            step,
            max,
            quantity,
            id: row.dataset.ballId,
            name: row.dataset.ballName,
            color: row.dataset.color,
            image: row.dataset.image || null,
            priceCents: Number(row.dataset.priceCents || 0),
        };
    }

    function render() {
        const states = rows.map(readRow);
        const selected = states.filter((state) => state.quantity > 0);

        const totalBalls = selected.reduce((sum, state) => sum + state.quantity, 0);
        const subtotal = selected.reduce((sum, state) => sum + state.quantity * state.priceCents, 0);
        const shipping = totalBalls > 0 ? shippingCents : 0;

        if (totalBallsEl) {
            totalBallsEl.textContent = count.format(totalBalls);
        }

        if (subtotalEl) {
            subtotalEl.textContent = money.format(subtotal / 100);
        }

        if (shippingEl) {
            shippingEl.textContent = shipping > 0 ? money.format(shipping / 100) : '—';
        }

        if (totalEl) {
            totalEl.textContent = money.format((subtotal + shipping) / 100);
        }

        if (submitEl) {
            submitEl.disabled = totalBalls === 0;
        }

        if (emptyStateEl) {
            emptyStateEl.hidden = totalBalls > 0;
        }

        // Highlight the rows that are actually part of the mix.
        for (const state of states) {
            state.row.dataset.selected = state.quantity > 0 ? 'true' : 'false';
        }

        renderLegend(selected, totalBalls);

        // Totals update on every keystroke, but the pit waits a beat: typing
        // "5000" should pour once, not four times.
        queuePitUpdate(
            selected.map((state) => ({
                id: state.id,
                color: state.color,
                image: state.image,
                quantity: state.quantity,
            })),
        );
    }

    let pitUpdateTimer = null;

    function queuePitUpdate(mix) {
        if (pitUpdateTimer !== null) {
            clearTimeout(pitUpdateTimer);
        }

        pitUpdateTimer = setTimeout(() => {
            pitUpdateTimer = null;
            pit.setMix(mix);
        }, 140);
    }

    function renderLegend(selected, totalBalls) {
        if (!legendEl) {
            return;
        }

        legendEl.replaceChildren();

        const sorted = selected.slice().sort((a, b) => b.quantity - a.quantity);

        for (const state of sorted) {
            const share = totalBalls > 0 ? Math.round((state.quantity / totalBalls) * 100) : 0;

            const item = document.createElement('li');
            item.className = 'flex items-center gap-2 text-sm';

            let swatch;

            if (state.image) {
                swatch = document.createElement('img');
                swatch.src = state.image;
                swatch.alt = '';
                swatch.className = 'size-4 shrink-0 rounded-full border-2 border-pit-950 object-cover';
            } else {
                swatch = document.createElement('span');
                swatch.className = 'size-4 shrink-0 rounded-full border-2 border-pit-950';
                swatch.style.backgroundColor = state.color;
            }

            const label = document.createElement('span');
            label.className = 'flex-1 truncate font-medium text-pit-800';
            label.textContent = state.name;

            const value = document.createElement('span');
            value.className = 'font-display font-bold tabular-nums text-pit-950';
            value.textContent = `${count.format(state.quantity)} · ${share}%`;

            item.append(swatch, label, value);
            legendEl.append(item);
        }
    }

    function setQuantity(input, quantity) {
        const max = Number(input.max || 0);
        const clamped = Math.max(0, max ? Math.min(quantity, max) : quantity);

        input.value = clamped === 0 ? '' : String(clamped);

        render();
    }

    for (const row of rows) {
        const input = row.querySelector('[data-qty-input]');

        input.addEventListener('input', render);
        input.addEventListener('change', () => {
            setQuantity(input, Number(input.value) || 0);
        });

        row.querySelector('[data-step-up]')?.addEventListener('click', () => {
            const { step, quantity } = readRow(row);
            // Snap up to the next multiple of the step.
            setQuantity(input, Math.floor(quantity / step) * step + step);
        });

        row.querySelector('[data-step-down]')?.addEventListener('click', () => {
            const { step, quantity } = readRow(row);
            setQuantity(input, Math.max(0, Math.ceil(quantity / step) * step - step));
        });

        for (const chip of row.querySelectorAll('[data-quick-add]')) {
            chip.addEventListener('click', () => {
                const amount = Number(chip.dataset.quickAdd || 0);
                setQuantity(input, readRow(row).quantity + amount);
            });
        }

        row.querySelector('[data-clear]')?.addEventListener('click', () => {
            setQuantity(input, 0);
        });
    }

    root.querySelector('[data-clear-all]')?.addEventListener('click', () => {
        for (const row of rows) {
            row.querySelector('[data-qty-input]').value = '';
        }

        render();
    });

    render();

    if (pitUpdateTimer !== null) {
        clearTimeout(pitUpdateTimer);
        pitUpdateTimer = null;
    }

    pit.setMix(
        rows
            .map(readRow)
            .filter((state) => state.quantity > 0)
            .map((state) => ({
                id: state.id,
                color: state.color,
                image: state.image,
                quantity: state.quantity,
            })),
    );
}
