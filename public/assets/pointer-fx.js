// VibeCraft pointer effects: one canvas, one recipe per theme, every pointer state handled.
// Loaded only when the site owner turned effects on and the visitor did not ask for reduced motion.
// The system cursor is never replaced, so text, link, wait and not-allowed cursors keep working.
const STRENGTH = { subtle: 0.55, normal: 1, lively: 1.7 };
const FIELD = 'input:not([type="checkbox"], [type="radio"], [type="submit"], [type="button"], [type="reset"], [type="range"], [type="color"]), textarea, select, [contenteditable]:not([contenteditable="false"]), iframe';
const ACTIVE = 'a[href], button, summary, label, [role="button"], [role="tab"], input[type="submit"], input[type="button"], input[type="checkbox"], input[type="radio"]';
const DENIED = ':disabled, [aria-disabled="true"]';
const TAU = Math.PI * 2;
const rand = (min, max) => min + Math.random() * (max - min);
const pick = list => list[Math.floor(Math.random() * list.length)];
const ease = t => 1 - (1 - t) ** 3;

function readColors() {
    const style = getComputedStyle(document.documentElement);
    const read = (name, fallback) => style.getPropertyValue(name).trim() || fallback;
    const light = document.querySelector('meta[name="color-scheme"]')?.content === 'light';
    const ink = read('--c-ink', '#f2f5f5');
    const bg = read('--c-bg', '#10151a');
    const accent = read('--c-accent', '#3db7a6');
    return {
        light, ink, bg, accent,
        muted: read('--c-muted', '#9aa3ab'), focus: read('--c-focus', accent), danger: read('--c-danger', '#ff8a7a'),
        glow: light ? ink : read('--prism-white', '#ffffff'), outline: light ? ink : bg,
        rose: read('--prism-rose', '#ff6b8b'), sky: read('--prism-sky', '#5cc8ff'), gold: read('--prism-gold', '#ffe08a'),
        spark: read('--forge-spark', '#ffd27a'), grass: read('--arcade-grass', '#58c35e'), red: read('--arcade-red', '#b8321d'),
        edge: read('--arcade-edge', '#0a0d22'), yellow: read('--brick-yellow', '#ffd60a'), pink: read('--brick-pink', '#ff8fd8'),
        green: read('--brick-green', '#19c37d'), orange: read('--brick-orange', '#ff5a36'), cyan: read('--dusk-cyan', '#63f5ff'),
        sun: read('--dusk-sun', '#ffc271'),
    };
}

// A jagged line between two points, used by the lightning recipe.
function jag(x0, y0, x1, y1, amplitude) {
    const dx = x1 - x0, dy = y1 - y0, length = Math.hypot(dx, dy) || 1, nx = -dy / length, ny = dx / length;
    const points = [[x0, y0]];
    for (let step = 1; step < 3; step++) {
        const offset = rand(-amplitude, amplitude);
        points.push([x0 + dx * step / 3 + nx * offset, y0 + dy * step / 3 + ny * offset]);
    }
    points.push([x1, y1]);
    return points;
}

function radial(fx, count, speed, make) {
    for (let index = 0; index < count; index++) {
        const angle = rand(0, TAU), velocity = rand(speed[0], speed[1]);
        fx.add(make(Math.cos(angle) * velocity, Math.sin(angle) * velocity));
    }
}

const amount = (fx, base, power) => Math.max(2, Math.round(base * power * fx.mult));

// Each recipe: spacing (px of travel per trail mark), trail, burst (press or tap), optional denied/tap/follow, draw.
const RECIPES = {
    // Atlas: motes rise from the path; a press sends rings across the water.
    ripple: {
        spacing: 18,
        trail(fx, x, y, fromX, fromY, hot) {
            fx.add({ kind: 'mote', x: x + rand(-2, 2), y: y + rand(-2, 2), vx: rand(-10, 10), vy: rand(-28, -12), drag: 0.97, life: rand(0.55, 0.85), size: rand(1.4, hot ? 3.4 : 2.6), color: hot ? fx.c.focus : fx.c.accent });
        },
        burst(fx, x, y, power) {
            const rings = power >= 1 ? 3 : 2;
            for (let index = 0; index < rings; index++) {
                fx.add({ kind: 'ring', x, y, life: 0.7 + index * 0.1, delay: index * 0.09, size: (26 + index * 12) * (0.6 + 0.4 * power), color: index ? fx.c.accent : fx.c.focus });
            }
        },
        draw(ctx, p, t) {
            ctx.globalAlpha = t;
            if (p.kind === 'ring') {
                ctx.strokeStyle = p.color;
                ctx.lineWidth = 0.6 + 2 * t;
                ctx.beginPath();
                ctx.arc(p.x, p.y, 3 + p.size * ease(1 - t), 0, TAU);
                ctx.stroke();
                return;
            }
            ctx.fillStyle = p.color;
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.size * (0.5 + 0.5 * t), 0, TAU);
            ctx.fill();
        },
    },
    // Forge: flickering embers drift upward; a press strikes sparks like a hammer on an anvil.
    embers: {
        spacing: 13,
        trail(fx, x, y, fromX, fromY, hot) {
            fx.add({ kind: 'ember', x: x + rand(-3, 3), y: y + rand(-2, 2), vx: rand(-16, 16), vy: rand(-70, -30), gravity: -24, drag: 0.985, life: rand(0.5, 0.95), size: rand(1.4, hot ? 3.2 : 2.6), color: pick([fx.c.accent, fx.c.spark, fx.c.spark]), seed: rand(0, TAU) });
        },
        burst(fx, x, y, power) {
            fx.add({ kind: 'flash', x, y, life: 0.16, size: 10 + 8 * power, color: fx.c.spark });
            for (let index = 0, count = amount(fx, 12, power); index < count; index++) {
                const angle = rand(-Math.PI * 0.95, -Math.PI * 0.05), speed = rand(140, 320) * (0.6 + 0.4 * power);
                fx.add({ kind: 'spark', x, y, vx: Math.cos(angle) * speed, vy: Math.sin(angle) * speed, gravity: 720, drag: 0.99, life: rand(0.35, 0.65), color: pick([fx.c.spark, fx.c.accent]) });
            }
        },
        denied(fx, x, y) {
            for (let index = 0; index < 4; index++) {
                fx.add({ kind: 'smoke', x: x + rand(-4, 4), y, vx: rand(-12, 12), vy: rand(-40, -20), life: rand(0.6, 0.9), size: rand(4, 7), color: fx.c.muted });
            }
        },
        draw(ctx, p, t) {
            ctx.fillStyle = p.color;
            ctx.strokeStyle = p.color;
            if (p.kind === 'spark') {
                ctx.globalAlpha = t;
                ctx.lineWidth = 1.6;
                ctx.lineCap = 'round';
                ctx.beginPath();
                ctx.moveTo(p.x, p.y);
                ctx.lineTo(p.x - p.vx * 0.028, p.y - p.vy * 0.028);
                ctx.stroke();
                return;
            }
            if (p.kind === 'flash' || p.kind === 'smoke') {
                ctx.globalAlpha = p.kind === 'flash' ? t * 0.7 : t * 0.35;
                ctx.beginPath();
                ctx.arc(p.x, p.y, p.kind === 'flash' ? p.size * (1.3 - t * 0.3) : p.size * (2 - t), 0, TAU);
                ctx.fill();
                return;
            }
            const flicker = 0.6 + 0.4 * Math.sin(p.age * 22 + p.seed);
            ctx.globalAlpha = t * flicker * 0.25;
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.size * 3, 0, TAU);
            ctx.fill();
            ctx.globalAlpha = t * flicker;
            ctx.translate(p.x, p.y);
            ctx.rotate(Math.PI / 4);
            ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size);
        },
    },
    // Volt: a crackling arc follows the pointer; a press throws lightning in every direction.
    bolt: {
        spacing: 22,
        trail(fx, x, y, fromX, fromY, hot) {
            fx.add({ kind: 'arc', x, y, life: 0.32, points: jag(fromX, fromY, x, y, hot ? 9 : 6), color: hot ? fx.c.focus : fx.c.accent });
        },
        burst(fx, x, y, power) {
            fx.add({ kind: 'flash', x, y, life: 0.12, size: 6 + 14 * power, color: fx.c.focus });
            const count = amount(fx, 5, power), offset = rand(0, TAU);
            for (let index = 0; index < count; index++) {
                const angle = offset + index / count * TAU + rand(-0.3, 0.3), length = rand(28, 58) * (0.6 + 0.4 * power);
                fx.add({ kind: 'arc', x, y, life: 0.3, points: jag(x, y, x + Math.cos(angle) * length, y + Math.sin(angle) * length, 7), color: pick([fx.c.accent, fx.c.focus]) });
            }
        },
        draw(ctx, p, t) {
            ctx.strokeStyle = p.color;
            ctx.fillStyle = p.color;
            if (p.kind === 'flash') {
                ctx.globalAlpha = t * 0.6;
                ctx.beginPath();
                ctx.arc(p.x, p.y, p.size, 0, TAU);
                ctx.fill();
                return;
            }
            if (Math.random() < 0.12) return;
            ctx.lineJoin = 'miter';
            for (const [width, alpha] of [[6, 0.18], [1.8, 1]]) {
                ctx.globalAlpha = t * alpha;
                ctx.lineWidth = width;
                ctx.beginPath();
                p.points.forEach(([px, py], index) => (index ? ctx.lineTo(px, py) : ctx.moveTo(px, py)));
                ctx.stroke();
            }
        },
    },
    // Nomad: footprints mark the route, left and right; a press raises a puff of sand.
    footprints: {
        spacing: 30,
        trail(fx, x, y, fromX, fromY, hot) {
            const angle = Math.atan2(y - fromY, x - fromX), side = (fx.state.step = (fx.state.step || 0) + 1) % 2 ? 1 : -1;
            fx.add({ kind: 'print', x: x + Math.cos(angle + Math.PI / 2) * 5 * side, y: y + Math.sin(angle + Math.PI / 2) * 5 * side, rot: angle, life: 1.5, color: hot ? fx.c.focus : fx.c.accent });
        },
        burst(fx, x, y, power) {
            radial(fx, amount(fx, 10, power), [30, 120 * (0.6 + 0.4 * power)], (vx, vy) => ({ kind: 'grain', x, y, vx, vy: vy - 20, gravity: 60, drag: 0.92, life: rand(0.5, 0.9), size: rand(1, 2.4), color: pick([fx.c.accent, fx.c.muted, fx.c.ink]) }));
        },
        draw(ctx, p, t) {
            ctx.fillStyle = p.color;
            if (p.kind === 'grain') {
                ctx.globalAlpha = t;
                ctx.fillRect(p.x, p.y, p.size, p.size);
                return;
            }
            ctx.globalAlpha = t * 0.75;
            ctx.translate(p.x, p.y);
            ctx.rotate(p.rot);
            ctx.beginPath();
            ctx.ellipse(0, 0, 5, 2.8, 0, 0, TAU);
            ctx.fill();
            for (const toe of [-2.2, 0, 2.2]) {
                ctx.beginPath();
                ctx.arc(7, toe, 1.1, 0, TAU);
                ctx.fill();
            }
        },
    },
    // Mono: a quiet ring trails the cursor, opens over links and stamps a cross on press.
    ring: {
        spacing: Infinity,
        follow(ctx, f, pointer, c) {
            ctx.globalAlpha = f.alpha;
            ctx.strokeStyle = pointer.hot ? c.accent : c.ink;
            ctx.lineWidth = pointer.hot ? 2 : 1.4;
            ctx.beginPath();
            ctx.arc(f.x, f.y, f.r, 0, TAU);
            ctx.stroke();
            if (pointer.hot) {
                ctx.globalAlpha = f.alpha * 0.12;
                ctx.fillStyle = c.accent;
                ctx.fill();
            }
        },
        burst(fx, x, y, power) {
            fx.add({ kind: 'cross', x, y, life: 0.5, size: 7 + 7 * power, color: fx.c.accent });
        },
        tap(fx, x, y) {
            fx.add({ kind: 'tapring', x, y, life: 0.55, size: 28, color: fx.c.ink });
            fx.add({ kind: 'cross', x, y, life: 0.5, size: 10, color: fx.c.accent });
        },
        draw(ctx, p, t) {
            ctx.globalAlpha = t;
            ctx.strokeStyle = p.color;
            ctx.lineWidth = 1.5;
            ctx.beginPath();
            if (p.kind === 'tapring') {
                ctx.arc(p.x, p.y, 6 + p.size * ease(1 - t), 0, TAU);
            } else {
                const size = p.size * (0.6 + 0.4 * ease(1 - t));
                ctx.moveTo(p.x - size, p.y);
                ctx.lineTo(p.x + size, p.y);
                ctx.moveTo(p.x, p.y - size);
                ctx.lineTo(p.x, p.y + size);
            }
            ctx.stroke();
        },
    },
    // Prism: light splits into rose, white and sky along the path; a press shatters into glass shards.
    prism: {
        spacing: 9,
        blend: 'lighter',
        trail(fx, x, y, fromX, fromY, hot) {
            fx.add({ kind: 'streak', x, y, x0: fromX, y0: fromY, life: hot ? 0.55 : 0.45 });
        },
        burst(fx, x, y, power) {
            radial(fx, amount(fx, 9, power), [90, 220 * (0.6 + 0.4 * power)], (vx, vy) => ({ kind: 'shard', x, y, vx, vy, drag: 0.92, spin: rand(-9, 9), rot: rand(0, TAU), life: rand(0.5, 0.8), size: rand(4, 8), color: pick([fx.c.rose, fx.c.sky, fx.c.gold, fx.c.accent]) }));
        },
        draw(ctx, p, t, c) {
            if (p.kind === 'shard') {
                ctx.globalAlpha = t;
                ctx.translate(p.x, p.y);
                ctx.rotate(p.rot);
                ctx.fillStyle = p.color;
                ctx.beginPath();
                ctx.moveTo(0, -p.size);
                ctx.lineTo(p.size * 0.6, p.size * 0.7);
                ctx.lineTo(-p.size * 0.6, p.size * 0.7);
                ctx.closePath();
                ctx.fill();
                ctx.globalAlpha = t * 0.8;
                ctx.strokeStyle = c.glow;
                ctx.lineWidth = 0.8;
                ctx.beginPath();
                ctx.moveTo(0, -p.size);
                ctx.lineTo(-p.size * 0.6, p.size * 0.7);
                ctx.stroke();
                return;
            }
            const dx = p.x - p.x0, dy = p.y - p.y0, length = Math.hypot(dx, dy) || 1, nx = -dy / length, ny = dx / length;
            ctx.lineCap = 'round';
            ctx.lineWidth = 0.6 + 2 * t;
            for (const [color, offset] of [[c.rose, -1.8], [c.glow, 0], [c.sky, 1.8]]) {
                ctx.globalAlpha = t * (offset ? 0.75 : 0.9);
                ctx.strokeStyle = color;
                ctx.beginPath();
                ctx.moveTo(p.x0 + nx * offset, p.y0 + ny * offset);
                ctx.lineTo(p.x + nx * offset, p.y + ny * offset);
                ctx.stroke();
            }
        },
    },
    // Arcade: the path leaves pixels on a 6px grid; a press pops coins that jump and fall.
    pixels: {
        spacing: 8,
        trail(fx, x, y, fromX, fromY, hot) {
            const gx = Math.round(x / 6) * 6, gy = Math.round(y / 6) * 6, cell = gx + ':' + gy;
            if (fx.state.cell === cell) return;
            fx.state.cell = cell;
            fx.add({ kind: 'px', x: gx, y: gy, life: 0.45, color: hot ? fx.c.accent : pick([fx.c.accent, fx.c.grass, fx.c.focus, fx.c.ink]) });
        },
        burst(fx, x, y, power) {
            for (let index = 0, count = amount(fx, 7, power); index < count; index++) {
                fx.add({ kind: 'coin', x, y, vx: rand(-130, 130), vy: rand(-360, -200) * (0.6 + 0.4 * power), gravity: 1150, life: rand(0.55, 0.8), color: fx.c.accent });
            }
        },
        denied(fx, x, y) {
            fx.add({ kind: 'cross', x, y, life: 0.45, color: fx.c.red });
        },
        draw(ctx, p, t, c) {
            ctx.globalAlpha = Math.ceil(t * 4) / 4;
            const x = Math.round(p.x / 2) * 2, y = Math.round(p.y / 2) * 2;
            if (p.kind === 'coin') {
                ctx.fillStyle = c.edge;
                ctx.fillRect(x - 5, y - 5, 10, 10);
                ctx.fillStyle = p.color;
                ctx.fillRect(x - 3, y - 3, 6, 6);
                ctx.fillStyle = c.glow;
                ctx.fillRect(x - 3, y - 3, 2, 2);
                return;
            }
            ctx.fillStyle = p.color;
            if (p.kind === 'cross') {
                for (let step = -2; step <= 2; step++) {
                    ctx.fillRect(x + step * 3 - 1.5, y + step * 3 - 1.5, 3, 3);
                    ctx.fillRect(x + step * 3 - 1.5, y - step * 3 - 1.5, 3, 3);
                }
                return;
            }
            ctx.fillRect(x - 3, y - 3, 6, 6);
        },
    },
    // Terminal: characters appear on a text grid; a press drops a blinking block cursor and a scan line.
    glyphs: {
        spacing: 15,
        trail(fx, x, y, fromX, fromY, hot) {
            const gx = Math.round(x / 12) * 12, gy = Math.round(y / 18) * 18, cell = gx + ':' + gy;
            if (fx.state.cell === cell) return;
            fx.state.cell = cell;
            fx.add({ kind: 'glyph', x: gx, y: gy, life: 0.75, ch: pick(hot ? ['>', '_', '[', ']'] : ['0', '1', '0', '1', '<', '>', '/', '*', '#', '=']), color: hot ? fx.c.ink : fx.c.accent });
        },
        burst(fx, x, y, power) {
            fx.add({ kind: 'block', x: Math.round(x / 12) * 12, y: Math.round(y / 18) * 18, life: 0.6, color: fx.c.accent });
            fx.add({ kind: 'scan', x, y, life: 0.35, size: 30 + 90 * power, color: fx.c.accent });
        },
        denied(fx, x, y) {
            fx.add({ kind: 'glyph', x, y: y - 10, vy: -20, life: 0.6, ch: '!', color: fx.c.danger });
        },
        draw(ctx, p, t) {
            ctx.fillStyle = p.color;
            ctx.strokeStyle = p.color;
            if (p.kind === 'block') {
                if (Math.floor(p.age * 8) % 2) return;
                ctx.globalAlpha = 0.85;
                ctx.fillRect(p.x - 5, p.y - 9, 10, 18);
                return;
            }
            if (p.kind === 'scan') {
                const width = p.size * ease(1 - t);
                ctx.globalAlpha = t;
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.moveTo(p.x - width, p.y);
                ctx.lineTo(p.x + width, p.y);
                ctx.stroke();
                return;
            }
            ctx.globalAlpha = Math.ceil(t * 3) / 3;
            ctx.font = '20px VT323, ui-monospace, monospace';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(p.ch, p.x, p.y);
        },
    },
    // Brick: outlined toy blocks tumble off the path; a press throws a handful into the air.
    blocks: {
        spacing: 24,
        trail(fx, x, y, fromX, fromY, hot) {
            fx.add({ kind: 'block', x, y, vx: rand(-24, 24), vy: rand(-30, 10), gravity: 280, spin: rand(-3, 3), rot: rand(-0.4, 0.4), life: 0.9, size: rand(7, hot ? 13 : 11), color: pick([fx.c.yellow, fx.c.pink, fx.c.green, fx.c.orange, fx.c.accent]) });
        },
        burst(fx, x, y, power) {
            for (let index = 0, count = amount(fx, 10, power); index < count; index++) {
                fx.add({ kind: 'block', x, y, vx: rand(-210, 210), vy: rand(-400, -190) * (0.6 + 0.4 * power), gravity: 1150, spin: rand(-10, 10), rot: rand(0, TAU), life: rand(0.6, 0.9), size: rand(8, 13), color: pick([fx.c.yellow, fx.c.pink, fx.c.green, fx.c.orange, fx.c.accent]) });
            }
        },
        denied(fx, x, y) {
            fx.add({ kind: 'block', x, y, vy: -60, gravity: 500, life: 0.5, size: 10, color: fx.c.muted });
        },
        draw(ctx, p, t, c) {
            const size = p.size * Math.min(1, t * 3);
            ctx.globalAlpha = Math.min(1, t * 2);
            ctx.translate(p.x, p.y);
            ctx.rotate(p.rot);
            ctx.fillStyle = c.outline;
            ctx.fillRect(-size / 2 + 3, -size / 2 + 3, size, size);
            ctx.fillStyle = p.color;
            ctx.fillRect(-size / 2, -size / 2, size, size);
            ctx.strokeStyle = c.outline;
            ctx.lineWidth = 2;
            ctx.strokeRect(-size / 2, -size / 2, size, size);
        },
    },
    // Dusk: a neon ribbon fades from pink to cyan; a press pulses a ring and lights a few stars.
    neon: {
        spacing: 6,
        blend: 'lighter',
        trail(fx, x, y, fromX, fromY, hot) {
            fx.add({ kind: 'seg', x, y, x0: fromX, y0: fromY, life: hot ? 0.55 : 0.45, color: hot ? fx.c.cyan : fx.c.accent, tail: fx.c.cyan });
        },
        burst(fx, x, y, power) {
            fx.add({ kind: 'ring', x, y, life: 0.6, size: 34 * (0.6 + 0.4 * power), color: fx.c.cyan });
            for (let index = 0, count = amount(fx, 4, power); index < count; index++) {
                fx.add({ kind: 'star', x: x + rand(-26, 26), y: y + rand(-26, 26), life: rand(0.5, 0.8), delay: rand(0, 0.15), size: rand(4, 7), spin: rand(-2, 2), color: fx.c.sun });
            }
        },
        draw(ctx, p, t) {
            ctx.strokeStyle = p.kind === 'seg' && t < 0.5 ? p.tail : p.color;
            ctx.fillStyle = p.color;
            ctx.lineCap = 'round';
            if (p.kind === 'star') {
                const size = p.size * (0.4 + 0.6 * t);
                ctx.globalAlpha = t;
                ctx.translate(p.x, p.y);
                ctx.rotate(p.rot);
                ctx.beginPath();
                ctx.moveTo(0, -size);
                ctx.lineTo(size * 0.22, 0);
                ctx.lineTo(0, size);
                ctx.lineTo(-size * 0.22, 0);
                ctx.closePath();
                ctx.moveTo(-size, 0);
                ctx.lineTo(0, size * 0.22);
                ctx.lineTo(size, 0);
                ctx.lineTo(0, -size * 0.22);
                ctx.closePath();
                ctx.fill();
                return;
            }
            for (const [width, alpha] of [[7, 0.16], [2.2, 1]]) {
                ctx.globalAlpha = t * alpha;
                ctx.beginPath();
                if (p.kind === 'ring') {
                    ctx.lineWidth = width;
                    ctx.arc(p.x, p.y, 4 + p.size * ease(1 - t), 0, TAU);
                } else {
                    ctx.lineWidth = width * (0.4 + 0.6 * t);
                    ctx.moveTo(p.x0, p.y0);
                    ctx.lineTo(p.x, p.y);
                }
                ctx.stroke();
            }
        },
    },
};

// Pressing something that cannot be used: a few muted grains drop, whatever the theme.
function fizzle(fx, x, y) {
    for (let index = 0; index < 5; index++) {
        fx.add({ kind: 'fizzle', x: x + rand(-5, 5), y, vx: rand(-20, 20), vy: rand(-30, 0), gravity: 160, life: 0.45, size: 2, color: fx.c.muted });
    }
}

export const EFFECTS = Object.keys(RECIPES);

export function start({ effect, strength = 'normal', touch = false, scope = null }) {
    const canvas = document.createElement('canvas');
    canvas.className = 'pointer-fx';
    canvas.setAttribute('aria-hidden', 'true');
    document.body.append(canvas);
    const ctx = canvas.getContext('2d');
    const listeners = new AbortController();
    const on = (target, type, handler) => target.addEventListener(type, handler, { passive: true, signal: listeners.signal });
    const particles = [];
    const pointer = { x: 0, y: 0, ex: 0, ey: 0, travel: 0, inside: false, hot: false, quiet: false, down: false, selecting: false, seen: 0 };
    const follower = { x: 0, y: 0, r: 10, alpha: 0 };
    let recipe = null, mult = 1, width = 0, height = 0, frame = 0, last = 0, tap = null;
    const fx = {
        c: readColors(), mult, state: {}, pointer,
        add(particle) {
            if (particles.length >= Math.round(110 * mult)) particles.shift();
            particles.push({ vx: 0, vy: 0, gravity: 0, drag: 1, spin: 0, rot: 0, delay: 0, age: 0, ...particle });
            wake();
        },
    };

    function set(nextEffect, nextStrength = 'normal') {
        recipe = RECIPES[nextEffect] ?? null;
        mult = fx.mult = STRENGTH[nextStrength] ?? 1;
        fx.state = {};
        particles.length = 0;
        wake();
    }

    function resize() {
        const ratio = Math.min(2, window.devicePixelRatio || 1);
        width = innerWidth;
        height = innerHeight;
        canvas.width = Math.round(width * ratio);
        canvas.height = Math.round(height * ratio);
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    }

    function wake() {
        if (!frame) {
            last = performance.now();
            frame = requestAnimationFrame(tick);
        }
    }

    function tick(now) {
        const dt = Math.min(0.05, (now - last) / 1000);
        last = now;
        ctx.clearRect(0, 0, width, height);
        ctx.globalCompositeOperation = recipe?.blend && !fx.c.light ? recipe.blend : 'source-over';
        let alive = 0;
        for (const p of particles) {
            p.age += dt;
            if (p.age >= p.life + p.delay) continue;
            particles[alive++] = p;
            if (p.age < p.delay) continue;
            if (p.drag !== 1) {
                const keep = p.drag ** (dt * 60);
                p.vx *= keep;
                p.vy *= keep;
            }
            p.vy += p.gravity * dt;
            p.x += p.vx * dt;
            p.y += p.vy * dt;
            p.rot += p.spin * dt;
            const t = 1 - (p.age - p.delay) / p.life;
            ctx.save();
            if (p.kind === 'fizzle') {
                ctx.globalAlpha = t;
                ctx.fillStyle = p.color;
                ctx.fillRect(p.x, p.y, p.size, p.size);
            } else if (recipe) {
                recipe.draw(ctx, p, t, fx.c);
            }
            ctx.restore();
        }
        particles.length = alive;
        let following = false;
        if (recipe?.follow) {
            const wanted = pointer.inside && !pointer.quiet && now - pointer.seen < 2200 ? 1 : 0;
            if (follower.alpha < 0.01) {
                follower.x = pointer.x;
                follower.y = pointer.y;
            }
            follower.alpha += (wanted - follower.alpha) * Math.min(1, dt * 10);
            follower.x += (pointer.x - follower.x) * Math.min(1, dt * 16);
            follower.y += (pointer.y - follower.y) * Math.min(1, dt * 16);
            follower.r += ((pointer.down ? 7 : pointer.hot ? 20 : 11) - follower.r) * Math.min(1, dt * 12);
            if (follower.alpha > 0.01 || wanted) {
                following = true;
                ctx.save();
                recipe.follow(ctx, follower, pointer, fx.c);
                ctx.restore();
            }
        }
        frame = alive || following ? requestAnimationFrame(tick) : 0;
        if (!frame) ctx.clearRect(0, 0, width, height);
    }

    const element = event => (event.target instanceof Element ? event.target : null);
    const within = event => !scope || (element(event) !== null && scope.contains(element(event)));

    function press(x, y, target, kind) {
        if (!recipe || target?.closest(FIELD)) return;
        if (target?.closest(DENIED)) {
            (recipe.denied ?? fizzle)(fx, x, y);
            return;
        }
        const power = scope || target?.closest(ACTIVE) ? 1 : 0.55;
        (kind === 'tap' && recipe.tap ? recipe.tap : recipe.burst)(fx, x, y, power);
    }

    on(window, 'resize', () => { resize(); wake(); });
    on(window, 'pointermove', event => {
        if (event.pointerType === 'touch') return;
        if (!within(event)) {
            pointer.inside = false;
            return;
        }
        const target = element(event);
        const fresh = !pointer.inside;
        const dx = event.clientX - pointer.x, dy = event.clientY - pointer.y;
        pointer.inside = true;
        pointer.x = event.clientX;
        pointer.y = event.clientY;
        pointer.seen = performance.now();
        pointer.hot = Boolean(target?.closest(ACTIVE)) && !target.closest(DENIED);
        // Typing, form fields and drag-selecting text stay calm.
        pointer.quiet = Boolean(target?.closest(FIELD)) || (pointer.selecting && (event.buttons & 1) === 1);
        wake();
        const distance = Math.hypot(dx, dy);
        if (fresh || pointer.quiet || !recipe || !Number.isFinite(recipe.spacing) || !distance) {
            pointer.ex = pointer.x;
            pointer.ey = pointer.y;
            pointer.travel = 0;
            return;
        }
        pointer.travel += distance * (pointer.hot ? 1.4 : 1);
        const spacing = recipe.spacing / mult, ux = dx / distance, uy = dy / distance;
        for (let marks = 0; pointer.travel >= spacing; marks++) {
            pointer.travel -= spacing;
            if (marks >= 12) {
                pointer.travel = 0;
                break;
            }
            const x = pointer.x - ux * pointer.travel, y = pointer.y - uy * pointer.travel;
            recipe.trail(fx, x, y, pointer.ex, pointer.ey, pointer.hot);
            pointer.ex = x;
            pointer.ey = y;
        }
    });
    on(window, 'pointerdown', event => {
        if (!within(event) || event.button !== 0) return;
        if (event.pointerType === 'touch') {
            tap = touch ? { x: event.clientX, y: event.clientY, time: performance.now(), target: element(event) } : null;
            return;
        }
        const target = element(event);
        pointer.down = true;
        pointer.selecting = !target?.closest(ACTIVE) && !target?.closest(FIELD);
        press(event.clientX, event.clientY, target, 'press');
    });
    on(window, 'pointerup', event => {
        pointer.down = false;
        pointer.selecting = false;
        if (event.pointerType !== 'touch' || !tap) return;
        // A tap is short and still; scrolling or long presses are left alone.
        const moved = Math.hypot(event.clientX - tap.x, event.clientY - tap.y);
        if (moved < 12 && performance.now() - tap.time < 600) press(tap.x, tap.y, tap.target, 'tap');
        tap = null;
    });
    on(window, 'pointercancel', () => {
        tap = null;
        pointer.down = false;
        pointer.selecting = false;
    });
    on(document, 'pointerout', event => {
        if (!event.relatedTarget) pointer.inside = false;
    });
    on(window, 'blur', () => {
        pointer.inside = false;
        pointer.down = false;
    });
    on(document, 'visibilitychange', () => {
        if (document.hidden) {
            particles.length = 0;
            pointer.inside = false;
        }
    });

    function stop() {
        listeners.abort();
        cancelAnimationFrame(frame);
        frame = 0;
        canvas.remove();
    }
    on(matchMedia('(prefers-reduced-motion: reduce)'), 'change', event => {
        if (event.matches) stop();
    });

    resize();
    set(effect, strength);
    return { set, stop };
}
