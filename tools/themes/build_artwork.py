"""Draw the original landscape artwork of the Prism, Arcade, Terminal, Brick and Dusk themes.

Every picture is generated from code with fixed seeds, so the result is reproducible and contains no
third-party image, game texture or traced material. Run from the repository root:

    python tools/themes/build_artwork.py

Output: public/assets/images/<theme>-landscape.webp (1600x901) and <theme>-landscape-768.webp.
Requires Pillow.
"""
import math
import random
from pathlib import Path

from PIL import Image, ImageDraw

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'public' / 'assets' / 'images'
W, H, SCALE = 400, 226, 4
BAYER = [[0, 8, 2, 10], [12, 4, 14, 6], [3, 11, 1, 9], [15, 7, 13, 5]]
BAYER8 = [[0, 32, 8, 40, 2, 34, 10, 42], [48, 16, 56, 24, 50, 18, 58, 26], [12, 44, 4, 36, 14, 46, 6, 38],
          [60, 28, 52, 20, 62, 30, 54, 22], [3, 35, 11, 43, 1, 33, 9, 41], [51, 19, 59, 27, 49, 17, 57, 25],
          [15, 47, 7, 39, 13, 45, 5, 37], [63, 31, 55, 23, 61, 29, 53, 21]]


def rgb(value):
    value = value.lstrip('#')
    return tuple(int(value[i:i + 2], 16) for i in (0, 2, 4))


def mix(a, b, t):
    t = max(0.0, min(1.0, t))
    return tuple(round(a[i] + (b[i] - a[i]) * t) for i in range(3))


def canvas(color='#000000'):
    return Image.new('RGB', (W, H), rgb(color))


def dithered_sky(image, stops, until=H):
    """Vertical colour bands; neighbouring bands blend through an ordered dither, never a smooth ramp."""
    px = image.load()
    stops = [(y, rgb(c)) for y, c in stops]
    for y in range(until):
        for index in range(len(stops) - 1):
            if stops[index][0] <= y < stops[index + 1][0]:
                (y0, c0), (y1, c1) = stops[index], stops[index + 1]
                t = (y - y0) / (y1 - y0)
                for x in range(W):
                    px[x, y] = c1 if t > (BAYER[y % 4][x % 4] + 0.5) / 16 else c0
                break
        else:
            for x in range(W):
                px[x, y] = stops[-1][1]


def ridge(seed, base, amplitude, column=4, level=2, waves=4):
    """Stepped skyline: sums of sines sampled per block column and snapped to block rows."""
    rnd = random.Random(seed)
    parts = [(rnd.uniform(0.008, 0.05) * (index + 1), rnd.uniform(0, math.tau), 1 / (index + 1)) for index in range(waves)]
    total = sum(part[2] for part in parts)
    heights = []
    for x in range(W):
        sample = (x // column) * column
        value = sum(math.sin(sample * f + p) * a for f, p, a in parts) / total
        heights.append(int((base - value * amplitude) // level * level))
    return heights


def fill_ridge(image, heights, color, bottom=H):
    draw = ImageDraw.Draw(image)
    for x, top in enumerate(heights):
        draw.line([(x, top), (x, bottom)], fill=rgb(color))


def edge_ridge(image, heights, color, depth=1):
    """Lights the top edge of a ridge, including the risers between steps."""
    px = image.load()
    for x, top in enumerate(heights):
        for dy in range(depth):
            if 0 <= top + dy < H:
                px[x, top + dy] = rgb(color)
        if x and heights[x - 1] > top:
            for y in range(top, min(heights[x - 1], H)):
                px[x, y] = rgb(color)


def stars(image, seed, count, colors, below=110):
    rnd = random.Random(seed)
    px = image.load()
    for _ in range(count):
        x, y = rnd.randrange(W), rnd.randrange(below)
        px[x, y] = rgb(rnd.choice(colors))
        if rnd.random() < 0.08 and 1 < x < W - 2 and 1 < y < below - 2:
            for dx, dy in ((1, 0), (-1, 0), (0, 1), (0, -1)):
                px[x + dx, y + dy] = rgb(colors[0])


def block_tree(draw, x, ground, size, trunk, leaves, light):
    draw.rectangle([x + size // 2 - 1, ground - size, x + size // 2, ground], fill=rgb(trunk))
    draw.rectangle([x, ground - size * 2, x + size - 1, ground - size + 1], fill=rgb(leaves))
    draw.rectangle([x + 1, ground - size * 2 - 2, x + size - 2, ground - size * 2], fill=rgb(leaves))
    draw.rectangle([x + 1, ground - size * 2, x + size // 2, ground - size * 2 + 1], fill=rgb(light))


def cloud(draw, x, y, w, body, shade):
    rows = [(0.25, 0.7, 0), (0.1, 0.9, 3), (0.0, 1.0, 6), (0.08, 0.95, 9)]
    for start, end, dy in rows:
        draw.rectangle([x + int(w * start), y + dy, x + int(w * end), y + dy + 3], fill=rgb(body))
    draw.rectangle([x + int(w * 0.08), y + 11, x + int(w * 0.95), y + 12], fill=rgb(shade))


def export(image, name):
    OUT.mkdir(parents=True, exist_ok=True)
    big = image.resize((W * SCALE, H * SCALE), Image.NEAREST).crop((0, 0, 1600, 901))
    big.save(OUT / f'{name}-landscape.webp', 'WEBP', lossless=True, method=6)
    big.resize((768, 432), Image.BOX).save(OUT / f'{name}-landscape-768.webp', 'WEBP', quality=84, method=6)


def arcade():
    """A bright stepped overworld: banded sky, block clouds, a keep on the hill, layered grass terraces."""
    image = canvas()
    dithered_sky(image, [(0, '#2f6fd6'), (40, '#4d94ec'), (84, '#7fbdf7'), (124, '#b9e2ff'), (150, '#e4f5ff'), (H, '#e4f5ff')])
    draw = ImageDraw.Draw(image)
    draw.rectangle([318, 26, 345, 53], fill=rgb('#fff6c2'))
    draw.rectangle([314, 30, 349, 49], fill=rgb('#fff6c2'))
    draw.rectangle([322, 30, 341, 49], fill=rgb('#ffe066'))
    for x, y, w in ((20, 30, 54), (118, 58, 40), (196, 22, 62), (268, 76, 36), (352, 92, 44), (66, 92, 30)):
        cloud(draw, x, y, w, '#ffffff', '#cfe6fb')

    far = ridge(11, 128, 30, column=6, level=4)
    fill_ridge(image, far, '#8fb4e3')
    px = image.load()
    for x, top in enumerate(far):
        if top < 112:
            for y in range(top, min(top + 6, 112)):
                px[x, y] = rgb('#f4fbff')
    mid = ridge(23, 146, 20, column=5, level=3)
    fill_ridge(image, mid, '#5f93c9')
    edge_ridge(image, mid, '#7aa9da')

    back = ridge(31, 164, 16, column=8, level=4)
    fill_ridge(image, back, '#3d9a4c')
    edge_ridge(image, back, '#6fca62', depth=2)
    rnd = random.Random(5)
    for _ in range(26):
        x = rnd.randrange(8, W - 14)
        block_tree(draw, x, back[x] + 1, 6, '#6b4428', '#226b36', '#4fae52')

    # The keep stands on the highest point of the right-hand hill.
    kx = max(range(250, 340), key=lambda x: -back[x])
    base = back[kx] + 2
    draw.rectangle([kx - 16, base - 30, kx + 16, base], fill=rgb('#8d95a6'))
    draw.rectangle([kx - 16, base - 30, kx - 13, base], fill=rgb('#b4bccb'))
    draw.rectangle([kx + 12, base - 30, kx + 16, base], fill=rgb('#6c7384'))
    for offset in range(-16, 17, 8):
        draw.rectangle([kx + offset, base - 35, kx + offset + 3, base - 30], fill=rgb('#8d95a6'))
    draw.rectangle([kx - 6, base - 52, kx + 6, base - 30], fill=rgb('#9aa2b3'))
    draw.rectangle([kx - 6, base - 52, kx - 4, base - 30], fill=rgb('#c0c8d6'))
    for offset in (-6, -1, 4):
        draw.rectangle([kx + offset, base - 56, kx + offset + 2, base - 52], fill=rgb('#9aa2b3'))
    draw.rectangle([kx - 2, base - 12, kx + 2, base], fill=rgb('#3a2a1c'))
    draw.rectangle([kx - 10, base - 22, kx - 8, base - 18], fill=rgb('#ffd75a'))
    draw.rectangle([kx + 8, base - 22, kx + 10, base - 18], fill=rgb('#ffd75a'))
    draw.rectangle([kx - 1, base - 44, kx + 1, base - 40], fill=rgb('#ffd75a'))
    draw.line([(kx, base - 56), (kx, base - 70)], fill=rgb('#4a3526'))
    draw.rectangle([kx + 1, base - 70, kx + 10, base - 64], fill=rgb('#e2453c'))
    draw.rectangle([kx + 1, base - 67, kx + 6, base - 64], fill=rgb('#b92f2c'))

    lake_top = 178
    draw.rectangle([0, lake_top, W, 196], fill=rgb('#3f8fe0'))
    for y in range(lake_top + 2, 196, 4):
        for x in range((y * 7) % 23, W, 23):
            draw.line([(x, y), (x + 8, y)], fill=rgb('#8ecbff'))
    front = ridge(47, 190, 12, column=8, level=4)
    for x in range(W):
        front[x] = max(front[x], 176) if 120 < x < 250 else front[x]
    fill_ridge(image, front, '#49b356')
    edge_ridge(image, front, '#8be071', depth=2)
    for _ in range(9):
        x = rnd.choice([rnd.randrange(4, 110), rnd.randrange(262, W - 16)])
        block_tree(draw, x, front[x] + 1, 9, '#5c3a22', '#1e5f31', '#58bb58')

    # Foreground strata drawn as a block grid: grass cap, soil, stone.
    block = 8
    top = 202
    for bx in range(0, W, block):
        for row, (fill, speck) in enumerate((('#58c35e', '#3c9a47'), ('#96643f', '#74492c'), ('#7c8494', '#626a7a'))):
            y = top + row * block
            draw.rectangle([bx, y, bx + block - 1, y + block - 1], fill=rgb(fill))
            for _ in range(5):
                sx, sy = bx + rnd.randrange(block), y + rnd.randrange(block)
                px[sx, min(sy, H - 1)] = rgb(speck)
            draw.line([(bx, y + block - 1), (bx + block - 1, y + block - 1)], fill=rgb(speck))
        draw.line([(bx, top), (bx + block - 1, top)], fill=rgb('#a4ec83'))
    return image


def dusk():
    """A banded sunset with a slit sun, stepped ranges, a glowing gateway and a floor grid running to the horizon."""
    image = canvas()
    horizon = 150
    dithered_sky(image, [(0, '#150a2c'), (30, '#2b0f4f'), (62, '#5a1670'), (92, '#99246f'), (118, '#d9486a'),
                         (138, '#f7845a'), (horizon, '#ffc271'), (H, '#ffc271')], until=horizon)
    stars(image, 3, 150, ['#ffffff', '#ffd1f0', '#b8a6ff'], below=84)
    px = image.load()
    cx, cy, radius = 286, 98, 50
    for y in range(cy - radius, horizon):
        t = (y - (cy - radius)) / (radius * 2)
        color = mix(rgb('#fff0a0'), rgb('#ff8a4c'), t * 2) if t < 0.5 else mix(rgb('#ff8a4c'), rgb('#ff3f8e'), (t - 0.5) * 2)
        slit = t > 0.4 and ((y - cy) % 9) < 2 + int((t - 0.4) * 8)
        if slit:
            continue
        half = int(math.sqrt(max(0, radius * radius - (y - cy) ** 2)))
        for x in range(cx - half, cx + half + 1):
            if 0 <= x < W:
                px[x, y] = color

    far = ridge(71, 132, 26, column=6, level=3)
    fill_ridge(image, far, '#4a1466', bottom=horizon)
    edge_ridge(image, far, '#c43c8f')
    near = ridge(83, 144, 16, column=8, level=4)
    fill_ridge(image, near, '#22093d', bottom=horizon)
    edge_ridge(image, near, '#7a2a9c')

    draw = ImageDraw.Draw(image)
    gx, gy = 196, horizon
    draw.rectangle([gx - 15, gy - 44, gx + 15, gy], fill=rgb('#12051f'))
    draw.rectangle([gx - 9, gy - 38, gx + 9, gy - 1], fill=rgb('#ff5fb0'))
    for y in range(gy - 38, gy):
        for x in range(gx - 9, gx + 10):
            if (x * 3 + y * 5) % 7 < 2:
                px[x, y] = rgb('#ffd0ec')
            elif (x + y) % 5 == 0:
                px[x, y] = rgb('#c0329a')
    draw.rectangle([gx - 15, gy - 44, gx + 15, gy - 43], fill=rgb('#5b2a86'))
    draw.rectangle([gx - 15, gy - 44, gx - 14, gy], fill=rgb('#5b2a86'))

    floor = rgb('#0e0420')
    draw.rectangle([0, horizon, W, H], fill=floor)
    vanish = gx
    line, glow = rgb('#ff4fa3'), rgb('#7d1f78')
    for index in range(-30, 31):
        x_bottom = vanish + index * 34
        steps = H - horizon
        for step in range(steps):
            t = step / steps
            x = round(vanish + (x_bottom - vanish) * (0.06 + t * 0.94))
            if 0 <= x < W:
                px[x, horizon + step] = line if t > 0.25 else glow
    row = 0
    y = float(horizon)
    gap = 1.6
    while y < H:
        yi = int(y)
        color = line if row > 3 else glow
        draw.line([(0, yi), (W, yi)], fill=color)
        y += gap
        gap *= 1.32
        row += 1
    draw.line([(0, horizon), (W, horizon)], fill=rgb('#ffd0ec'))
    for x in range(gx - 9, gx + 10):
        for step in range(1, 26):
            if (x + step) % 3 == 0 and px[x, horizon + step] == floor:
                px[x, horizon + step] = mix(rgb('#ff5fb0'), floor, step / 26)
    return image


def terminal():
    """A moonlit keep rendered as a four-tone phosphor picture with an ordered dither."""
    light = [[0.0] * W for _ in range(H)]
    horizon = 156
    for y in range(horizon):
        base = 0.05 + 0.3 * (y / horizon) ** 2.2
        for x in range(W):
            light[y][x] = base
    mx, my, mr = 292, 58, 26
    for y in range(my - mr * 3, my + mr * 3):
        for x in range(mx - mr * 3, mx + mr * 3):
            if 0 <= x < W and 0 <= y < horizon:
                d = math.hypot(x - mx, y - my)
                if d <= mr:
                    crater = any(math.hypot(x - cx, y - cy) < cr for cx, cy, cr in ((284, 50, 5), (300, 66, 7), (296, 44, 3), (280, 68, 4)))
                    light[y][x] = 0.72 if crater else 1.0
                elif d < mr * 3:
                    light[y][x] = max(light[y][x], 0.62 * (1 - (d - mr) / (mr * 2)) ** 2 + light[y][x])
    rnd = random.Random(9)
    for _ in range(120):
        x, y = rnd.randrange(W), rnd.randrange(100)
        if math.hypot(x - mx, y - my) > mr + 6:
            light[y][x] = 1.0

    for seed, base, amp, tone, rim in ((101, 124, 30, 0.34, 0.5), (113, 140, 20, 0.2, 0.36), (127, 152, 10, 0.1, 0.24)):
        heights = ridge(seed, base, amp, column=6, level=3)
        for x, top in enumerate(heights):
            for y in range(top, horizon):
                light[y][x] = tone
            if top < horizon:
                light[top][x] = rim

    def box(x0, y0, x1, y1, value):
        for y in range(max(0, y0), min(H, y1 + 1)):
            for x in range(max(0, x0), min(W, x1 + 1)):
                light[y][x] = value

    kx = 150
    box(kx - 34, horizon - 40, kx + 34, horizon, 0.07)
    for offset in range(-34, 35, 10):
        box(kx + offset, horizon - 46, kx + offset + 4, horizon - 40, 0.07)
    for tower in (-40, 34):
        box(kx + tower, horizon - 66, kx + tower + 12, horizon, 0.07)
        for offset in (0, 5, 10):
            box(kx + tower + offset, horizon - 71, kx + tower + offset + 2, horizon - 66, 0.07)
        box(kx + tower + 4, horizon - 56, kx + tower + 7, horizon - 49, 1.0)
        box(kx + tower + 12, horizon - 66, kx + tower + 12, horizon, 0.3)
    box(kx - 8, horizon - 78, kx + 8, horizon - 40, 0.07)
    for offset in (-8, -2, 5):
        box(kx + offset, horizon - 83, kx + offset + 3, horizon - 78, 0.07)
    box(kx - 2, horizon - 68, kx + 2, horizon - 60, 1.0)
    for wx in (-24, -12, 12, 24):
        box(kx + wx - 1, horizon - 28, kx + wx + 1, horizon - 22, 0.85)
    box(kx - 5, horizon - 16, kx + 5, horizon, 0.55)
    box(kx + 34, horizon - 40, kx + 34, horizon, 0.3)

    for y in range(horizon, H):
        depth = (y - horizon) / (H - horizon)
        for x in range(W):
            source = light[max(0, horizon - 1 - int((y - horizon) * 1.6))][min(W - 1, max(0, x + int(math.sin(y * 0.9) * 2 * depth * 3)))]
            light[y][x] = source * (0.55 - depth * 0.3) + (0.1 if (y - horizon) % 6 == 0 else 0)

    palette = [rgb('#03110a'), rgb('#0c4a22'), rgb('#27a34a'), rgb('#7dffa0')]
    image = canvas()
    px = image.load()
    for y in range(H):
        for x in range(W):
            value = max(0.0, min(0.999, light[y][x])) * (len(palette) - 1)
            level = int(value)
            if value - level > (BAYER8[y % 8][x % 8] + 0.5) / 64:
                level += 1
            px[x, y] = palette[min(level, len(palette) - 1)]
    return image


def prism():
    """Aurora curtains over a still lake, with faceted ice shards catching the colours."""
    image = canvas()
    horizon = 158
    dithered_sky(image, [(0, '#04060f'), (50, '#081127'), (104, '#0d2140'), (horizon, '#15375c'), (H, '#15375c')], until=horizon)
    stars(image, 17, 170, ['#ffffff', '#bfe9ff', '#ffe6fb'], below=120)
    px = image.load()
    curtains = [(0.021, 0.4, 62, 16, ('#39f0c4', '#7dff8a', '#c86bff')), (0.014, 2.1, 92, 12, ('#3fc9ff', '#39f0c4', '#ff7ad9')),
                (0.03, 4.0, 44, 10, ('#7dff8a', '#f5ff8a', '#ff7ad9'))]
    for freq, phase, base, sway, colors in curtains:
        low, mid, high = (rgb(c) for c in colors)
        for x in range(W):
            foot = base + math.sin(x * freq + phase) * sway + math.sin(x * freq * 2.7 + phase * 2) * sway * 0.35
            strength = 0.55 + 0.45 * math.sin(x * 0.11 + phase * 3) * math.sin(x * 0.043 + phase)
            length = 58 + 26 * math.sin(x * 0.05 + phase)
            if (x // 2) % 3 == 0:
                strength *= 0.55
            for step in range(int(length)):
                y = int(foot) - step
                if 0 <= y < horizon:
                    t = step / length
                    color = mix(low, mid, t * 2) if t < 0.5 else mix(mid, high, (t - 0.5) * 2)
                    alpha = max(0.0, strength) * (1 - t) ** 1.1 * 0.9
                    px[x, y] = mix(px[x, y], color, alpha)
    image = image.quantize(colors=40, dither=Image.Dither.NONE).convert('RGB')
    px = image.load()

    far = ridge(211, 126, 30, column=6, level=3)
    fill_ridge(image, far, '#12335a', bottom=horizon)
    edge_ridge(image, far, '#3f86b0')
    for x, top in enumerate(far):
        if top < 118:
            for y in range(top, min(top + 5 + (x // 6) % 3, 118)):
                px[x, y] = rgb('#d8f3ff')
    near = ridge(223, 152, 10, column=8, level=4)
    fill_ridge(image, near, '#081a30', bottom=horizon)
    edge_ridge(image, near, '#1c4767')

    draw = ImageDraw.Draw(image)
    for y in range(horizon, H):
        source = max(0, horizon - 1 - int((y - horizon) * 2.1))
        depth = (y - horizon) / (H - horizon)
        shift = int(math.sin(y * 1.3) * 3 * depth)
        for x in range(W):
            px[x, y] = mix(px[min(W - 1, max(0, x + shift)), source], rgb('#040915'), 0.42 + depth * 0.3)
        if (y - horizon) % 7 == 3:
            for x in range((y * 13) % 31, W, 31):
                draw.line([(x, y), (x + 12, y)], fill=rgb('#2e6f8f'))
    draw.line([(0, horizon), (W, horizon)], fill=rgb('#7fd9ee'))

    def shard(x, base, width, height, lean):
        tip = (x + lean, base - height)
        left, right, mid = (x - width // 2, base), (x + width // 2, base), (x + width // 6, base)
        draw.polygon([left, tip, mid], fill=rgb('#d6f6ff'))
        draw.polygon([mid, tip, right], fill=rgb('#57bfe6'))
        draw.polygon([(x + width // 3, base), tip, right], fill=rgb('#1d6c9c'))
        draw.line([left, tip], fill=rgb('#ffffff'))
        draw.line([tip, right], fill=rgb('#123f63'))
        glint = (x + lean // 2 - width // 6, base - height // 2)
        draw.line([glint, (glint[0] + 2, glint[1] - 5)], fill=rgb('#ffffff'))
        for step in range(1, min(22, H - base)):
            fade = step / 22
            for dx in range(-width // 2 + step // 2, width // 2 - step // 2):
                if (dx + step) % 2 == 0 and 0 <= x + dx < W:
                    px[x + dx, base + step] = mix(rgb('#8fdcf5'), px[x + dx, base + step], 0.45 + fade * 0.55)

    for x, width, height, lean in ((250, 16, 52, 6), (272, 22, 86, -4), (296, 18, 64, 8), (318, 12, 38, -3), (338, 20, 70, 5),
                                   (362, 14, 46, -5), (232, 10, 30, 3), (384, 16, 58, 2), (60, 10, 26, -2), (76, 14, 40, 4)):
        shard(x, horizon + 4, width, height, lean)
    return image


def brick():
    """A flat-colour block island with heavy outlines, drawn as vector shapes and reduced for clean edges."""
    factor = 2
    width, height = 1600 * factor, 901 * factor
    image = Image.new('RGB', (width, height), rgb('#fff1cf'))
    draw = ImageDraw.Draw(image)
    ink = rgb('#14110f')
    stroke = 5 * factor

    for gx in range(0, width, 40 * factor):
        for gy in range(0, height, 40 * factor):
            draw.rectangle([gx, gy, gx + 3 * factor, gy + 3 * factor], fill=rgb('#f0d9a8'))

    sun_x, sun_y, sun_r = 1290 * factor, 190 * factor, 96 * factor
    draw.ellipse([sun_x - sun_r, sun_y - sun_r, sun_x + sun_r, sun_y + sun_r], fill=rgb('#ffd60a'), outline=ink, width=stroke)
    for cx, cy, cw in ((250, 150, 230), (820, 110, 190), (1380, 420, 170)):
        x0, y0 = cx * factor, cy * factor
        draw.rounded_rectangle([x0 + 10 * factor, y0 + 10 * factor, x0 + cw * factor + 10 * factor, y0 + 64 * factor + 10 * factor], radius=32 * factor, fill=ink)
        draw.rounded_rectangle([x0, y0, x0 + cw * factor, y0 + 64 * factor], radius=32 * factor, fill=rgb('#ffffff'), outline=ink, width=stroke)

    tile_w, tile_h, unit = 92 * factor, 46 * factor, 54 * factor
    origin_x, origin_y = 800 * factor, 330 * factor
    palette = [('#ff5a36', '#c93f22', '#e24a2a'), ('#2f6bff', '#1f47b3', '#2757d6'), ('#19c37d', '#0f8a57', '#14a569'),
               ('#ffd60a', '#c9a400', '#e6bf00'), ('#ff8fd8', '#cc5aa6', '#e872bf'), ('#ffffff', '#c9c2b4', '#e6e0d2')]
    rnd = random.Random(42)
    size = 7
    heights = {}
    for gx in range(size):
        for gy in range(size):
            distance = math.hypot(gx - 3, gy - 3)
            if distance > 3.7:
                continue
            heights[(gx, gy)] = max(1, round(4.2 - distance + rnd.uniform(-0.8, 1.4)))
    heights[(3, 3)] = 6
    heights[(2, 4)] = 5

    def cube(gx, gy, level, colors):
        top, left, right = (rgb(c) for c in colors)
        x = origin_x + (gx - gy) * tile_w // 2
        y = origin_y + (gx + gy) * tile_h // 2 - level * unit
        north, east, south, west = (x, y), (x + tile_w // 2, y + tile_h // 2), (x, y + tile_h), (x - tile_w // 2, y + tile_h // 2)
        draw.polygon([west, south, (south[0], south[1] + unit), (west[0], west[1] + unit)], fill=left, outline=ink)
        draw.polygon([south, east, (east[0], east[1] + unit), (south[0], south[1] + unit)], fill=right, outline=ink)
        draw.polygon([north, east, south, west], fill=top, outline=ink)
        for a, b in ((north, east), (east, south), (south, west), (west, north), (west, (west[0], west[1] + unit)),
                     (south, (south[0], south[1] + unit)), (east, (east[0], east[1] + unit)),
                     ((west[0], west[1] + unit), (south[0], south[1] + unit)), ((south[0], south[1] + unit), (east[0], east[1] + unit))):
            draw.line([a, b], fill=ink, width=stroke)

    # Hard offset shadow of the whole island, then the cubes from back to front.
    shadow = 18 * factor
    for (gx, gy), _ in heights.items():
        x = origin_x + (gx - gy) * tile_w // 2 + shadow
        y = origin_y + (gx + gy) * tile_h // 2 + shadow
        draw.polygon([(x, y), (x + tile_w // 2, y + tile_h // 2), (x + tile_w // 2, y + tile_h // 2 + unit), (x, y + tile_h + unit),
                      (x - tile_w // 2, y + tile_h // 2 + unit), (x - tile_w // 2, y + tile_h // 2)], fill=ink)
    for gx in range(size):
        for gy in range(size):
            if (gx, gy) not in heights:
                continue
            column = heights[(gx, gy)]
            for level in range(column):
                top_level = level == column - 1
                colors = palette[(gx * 3 + gy * 5 + level) % len(palette)] if top_level else palette[5] if level % 2 else ('#ffe9b0', '#c9a45c', '#e6c47e')
                cube(gx, gy, level, colors)
    for gx, gy, level, index in ((-2, 2, 5, 1), (7, 1, 6, 0), (8, 5, 3, 2), (-1, 6, 2, 4)):
        cube(gx, gy, level, palette[index])
    return image.resize((1600, 901), Image.LANCZOS)


def export_flat(image, name):
    image.save(OUT / f'{name}-landscape.webp', 'WEBP', quality=90, method=6)
    image.resize((768, 432), Image.LANCZOS).save(OUT / f'{name}-landscape-768.webp', 'WEBP', quality=86, method=6)


def main():
    export(arcade(), 'arcade')
    export(dusk(), 'dusk')
    export(terminal(), 'terminal')
    export(prism(), 'prism')
    export_flat(brick(), 'brick')
    for path in sorted(OUT.glob('*-landscape*.webp')):
        print(path.name, path.stat().st_size)


if __name__ == '__main__':
    main()
