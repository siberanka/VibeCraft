"""Build the bundled VibeCraft voxel icon library.

Every icon is original pixel artwork described below as shapes on a small grid.
Shading and outlines are derived from the shape, so the whole set shares one
light direction (upper left) and one outline colour. Output:

  public/assets/library/<category>/<slug>.svg
  resources/media-library.json   (pack -> category -> icons, read by the app)

Usage: python tools/icons/build_library.py [--preview out.png]
The preview option needs Pillow and writes a contact sheet for review.
"""
import colorsys
import json
import math
import pathlib
import sys

ROOT = pathlib.Path(__file__).resolve().parents[2]
OUT = ROOT / 'public' / 'assets' / 'library'
MANIFEST = ROOT / 'resources' / 'media-library.json'
OUTLINE = '#15111a'

MATERIALS = {
    'wood': '#9a6634', 'stone': '#80808a', 'iron': '#c4cbd4', 'gold': '#f0b52a', 'diamond': '#3fd3cf',
    'obsidian': '#5b4a78', 'emerald': '#2fbf62', 'ruby': '#d8343f', 'amethyst': '#9a57d6', 'copper': '#cf7442',
    'leather': '#a4673a', 'lapis': '#3567d6', 'paper': '#efe6cf', 'cage': '#4a5160', 'flame': '#ff9a2e',
    'white': '#f4f6f8', 'ink': '#2a2433', 'green': '#5fae3c', 'pink': '#ef8fb8', 'sky': '#7cc8f2',
}


def tone(hex_colour, factor):
    r, g, b = (int(hex_colour[i:i + 2], 16) / 255 for i in (1, 3, 5))
    h, l, s = colorsys.rgb_to_hls(r, g, b)
    l = max(0.0, min(1.0, l + (1 - l) * factor if factor > 0 else l * (1 + factor)))
    r, g, b = colorsys.hls_to_rgb(h, l, s)
    return '#%02x%02x%02x' % tuple(round(c * 255) for c in (r, g, b))


def ramp(base):
    return {1: tone(base, -0.5), 2: tone(base, -0.25), 3: base, 4: tone(base, 0.28), 5: tone(base, 0.6)}


class Grid:
    def __init__(self, size=16):
        self.size = size
        self.cells = {}

    def put(self, x, y, material):
        if 0 <= x < self.size and 0 <= y < self.size:
            self.cells[(x, y)] = material

    def rows(self, rows, legend, ox=0, oy=0, mirror=False):
        """Paint a character map; with mirror the map is the left half of a symmetric shape."""
        for y, row in enumerate(rows):
            if mirror:
                row = row + row[::-1]
            for x, ch in enumerate(row):
                if ch in legend:
                    self.put(ox + x, oy + y, legend[ch])

    def svg(self, palettes, flat=()):
        colours = {}
        filled = self.cells
        for (x, y), material in filled.items():
            if material in flat or material.startswith('#'):
                colour = material if material.startswith('#') else palettes[material][3]
            else:
                def open_(dx, dy):
                    return (x + dx, y + dy) not in filled
                up, left, down, right = open_(0, -1), open_(-1, 0), open_(0, 1), open_(1, 0)
                step = 3
                if up and left:
                    step = 5
                elif up or left:
                    step = 4
                if down and right:
                    step = 1
                elif (down or right) and not (up or left):
                    step = 2
                colour = palettes[material][step]
            colours.setdefault(colour, []).append((x, y))
        for (x, y) in list(filled):
            for dx, dy in ((0, 1), (1, 0), (0, -1), (-1, 0)):
                n = (x + dx, y + dy)
                if n not in filled and 0 <= n[0] < self.size and 0 <= n[1] < self.size:
                    colours.setdefault(OUTLINE, [])
                    if n not in colours[OUTLINE]:
                        colours[OUTLINE].append(n)
        paths = []
        order = [OUTLINE] + [c for c in colours if c != OUTLINE]
        for colour in order:
            cells = sorted(set(colours.get(colour, [])), key=lambda c: (c[1], c[0]))
            d, run = [], None
            for x, y in cells + [(None, None)]:
                if run and x == run[0] + run[2] and y == run[1]:
                    run[2] += 1
                    continue
                if run:
                    d.append('M%d %dh%dv1h-%dz' % (run[0], run[1], run[2], run[2]))
                run = [x, y, 1] if x is not None else None
            if d:
                paths.append('<path fill="%s" d="%s"/>' % (colour, ''.join(d)))
        s = self.size
        return ('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="128" height="128" '
                'shape-rendering="crispEdges">%s</svg>\n') % (s, s, ''.join(paths))


def palettes_for(**materials):
    return {key: ramp(MATERIALS.get(value, value)) for key, value in materials.items()}


# ---------- diagonal tools (handle bottom-left, head top-right) ----------
def diagonal(grid, d_from, d_to, width, material):
    """Cells along the line x + y = 15; d = x - y runs from the handle (-15) to the tip (15)."""
    for x in range(grid.size):
        for y in range(grid.size):
            d, v = x - y, x + y - 15
            if d_from <= d <= d_to and abs(v) <= width:
                grid.put(x, y, material)


def sword(grid):
    diagonal(grid, -2, 12, 1, 'M')
    diagonal(grid, 13, 13, 0, 'M')
    diagonal(grid, -5, -4, 4, 'A')
    diagonal(grid, -10, -6, 0, 'H')
    diagonal(grid, -12, -11, 1, 'A')


def pickaxe(grid):
    diagonal(grid, -13, 8, 0, 'H')
    for x in range(grid.size):
        for y in range(grid.size):
            d, v = x - y, x + y - 15
            centre = 9 - (v * v) / 9.0
            if abs(v) <= 9 and centre - 1.2 <= d <= centre + 1.2:
                grid.put(x, y, 'M')


def axe(grid):
    diagonal(grid, -13, 12, 0, 'H')
    grid.rows(['................', '.........MMMM...', '........MMMMM...', '.......MMMMM....', '......MMMMM.....',
               '......MMMM......', '.......MM.......'], {'M': 'M'})


def shovel(grid):
    diagonal(grid, -13, 5, 0, 'H')
    for x in range(grid.size):
        for y in range(grid.size):
            d, v = x - y, x + y - 15
            if 6 <= d <= 13 and abs(v) <= 3 and not (d == 13 and abs(v) >= 2):
                grid.put(x, y, 'M')


def hoe(grid):
    diagonal(grid, -13, 9, 0, 'H')
    for x in range(grid.size):
        for y in range(grid.size):
            d, v = x - y, x + y - 15
            if 8 <= d <= 11 and -6 <= v <= 0:
                grid.put(x, y, 'M')


# ---------- symmetric shapes (left half, mirrored) ----------
HELMET = ['........', '........', '........', '....MMMM', '...MMMMM', '..MMMMMM', '..MMMMMM', '..MMMMMM',
          '..MMMMMM', '..MM....', '..MM....', '..MM....']
CHEST = ['........', '........', '.MMMM...', '.MMMMM..', '.MMMMMMM', '.MMMMMMM', '..MMMMMM', '...MMMMM',
         '...MMMMM', '...MMMMM', '...MMMMM', '...MMMMM', '...MMMMM', '...MMMMM']
LEGS = ['........', '........', '..MMMMMM', '..MMMMMM', '..MMMMMM', '..MMMMM.', '..MMMM..', '..MMMM..',
        '..MMMM..', '..MMMM..', '..MMMM..', '..MMMM..', '..MMMM..', '..MMMM..']
BOOTS = ['........', '........', '........', '........', '..MMM...', '..MMM...', '..MMM...', '..MMM...',
         '..MMMM..', '..MMMMM.', '.MMMMMM.', '.MMMMMM.', '.MMMMMM.']
CRATE = ['........', '........', '.MMMMMMM', '.MMMMMMM', '.MMMMMMM', '.MMMMMMA', '.AAAAAAA', '.MMMMMMA',
         '.MMMMMMM', '.MMMMMMM', '.MMMMMMM', '.MMMMMMM', '.MMMMMMM', '.MMMMMMM']
POTION = ['........', '......WW', '......WW', '.......W', '.......W', '.....GGG', '....GGGG', '...GGGGG',
          '...LLLLL', '...LLLLL', '...LLLLL', '...LLLLL', '....LLLL', '.....LLL']
CROWN = ['........', '........', '........', '........', '.A......', '.AA...A.', '.AAA.AAA', '.AAAAAAA',
         '.AAAGAAA', '.AAAAAAA', '.AAAAAAA', '.MMMMMMM']
TROPHY = ['........', '........', '.AAAAAAA', '.A.AAAAA', '.A.AAAAA', '..AAAAAA', '...AAAAA', '....AAAA',
          '......AA', '......AA', '.....AAA', '....MMMM', '....MMMM']
SHIELD = ['........', '..MMMMMM', '..MMMMMM', '..MMMMAA', '..MMMAAA', '..MMMMAA', '..MMMMMM', '..MMMMMM',
          '...MMMMM', '...MMMMM', '....MMMM', '.....MMM', '......MM']
HEART = ['........', '........', '........', '..MMM...', '.MMMMM.M', '.MMMMMMM', '.MMMMMMM', '.MMMMMMM',
         '..MMMMMM', '...MMMMM', '....MMMM', '.....MMM', '......MM', '.......M']
GIFT = ['........', '........', '....AA.A', '.....AAA', '.MMMMMMA', '.MMMMMMA', '.AAAAAAA', '.MMMMMMA',
        '..MMMMMA', '..MMMMMA', '..MMMMMA', '..MMMMMA', '..MMMMMA']
BELL = ['........', '........', '.......A', '......AA', '....AAAA', '....AAAA', '...AAAAA', '...AAAAA',
        '...AAAAA', '..AAAAAA', '.AAAAAAA', '.MMMMMMM', '.......A']
BAG = ['........', '........', '......LL', '.....LLL', '......AA', '.....LLL', '....LLLL', '...LLLLL',
       '..LLLLLL', '..LLLLLA', '..LLLLAA', '..LLLLLA', '...LLLLL', '....LLLL']


def mirrored(grid, rows, legend, oy=0):
    grid.rows(rows, legend, oy=oy, mirror=True)


def ellipse(grid, cx, cy, rx, ry, material):
    for x in range(grid.size):
        for y in range(grid.size):
            if ((x + 0.5 - cx) / rx) ** 2 + ((y + 0.5 - cy) / ry) ** 2 <= 1:
                grid.put(x, y, material)


def egg(grid, spots):
    for x in range(16):
        for y in range(16):
            ry = 6.4 if y + 0.5 < 9 else 5.2
            if ((x + 0.5 - 8) / 5.2) ** 2 + ((y + 0.5 - 9) / ry) ** 2 <= 1:
                grid.put(x, y, 'B')
    for x, y in spots:
        grid.put(x, y, 'S')
        grid.put(x + 1, y, 'S')
        grid.put(x, y + 1, 'S')
        grid.put(x + 1, y + 1, 'S')


EGG_SPOTS = [(5, 6), (9, 4), (10, 9), (6, 11), (4, 9)]


def key(grid):
    for x in range(16):
        for y in range(16):
            r = math.hypot(x + 0.5 - 4.5, y + 0.5 - 4.5)
            if 1.6 <= r <= 3.7:
                grid.put(x, y, 'M')
    for x in range(16):
        for y in range(16):
            if abs(x - y) <= 1 and 14 <= x + y <= 27:
                grid.put(x, y, 'M')
    for x, y in ((10, 12), (11, 13), (12, 14), (13, 11), (14, 12)):
        grid.put(x, y, 'M')


def spawner(grid):
    for x in range(1, 15):
        for y in range(1, 15):
            if x in (1, 14) or y in (1, 14) or (x in (5, 10) and y not in (6, 7, 8, 9)) or (y in (5, 10) and x not in (6, 7, 8, 9)):
                grid.put(x, y, 'K')
    for x in range(6, 10):
        for y in range(6, 10):
            grid.put(x, y, 'B')
    for x, y in ((3, 12), (12, 3), (3, 3), (12, 12)):
        grid.put(x, y, 'F')


def coin(grid):
    ellipse(grid, 8, 8, 6.2, 6.2, 'A')
    for x, y in ((7, 5), (8, 5), (7, 6), (7, 7), (8, 7), (8, 8), (8, 9), (7, 9), (8, 10), (7, 10)):
        grid.put(x, y, 'M')


def coins(grid):
    for i, cy in enumerate((12.5, 10, 7.5)):
        for x in range(16):
            for y in range(16):
                if ((x + 0.5 - 8) / 5.8) ** 2 + ((y + 0.5 - cy) / 2.6) ** 2 <= 1:
                    grid.put(x, y, 'A' if i % 2 == 0 else 'M')


def gem(grid):
    grid.rows(['....GGGG', '...GGGGG', '..GGGGGG', '.GGGGGGG', 'LLLLLLLL', '.GGGGGGG', '..GGGGGG', '...GGGGG',
               '....GGGG', '.....GGG', '......GG', '.......G'], {'G': 'G', 'L': 'L'}, oy=2, mirror=True)


def bar(grid):
    grid.rows(['....AAAA', '...AAAAA', '..AAAAAA', '.AAAAAAA', 'MMMMMMMM', 'MMMMMMMM'], {'A': 'A', 'M': 'M'}, oy=6, mirror=True)


def note(grid):
    for x in range(1, 15):
        for y in range(4, 12):
            grid.put(x, y, 'G')
    ellipse(grid, 8, 8, 2.2, 2.2, 'L')


def star(grid):
    pts = []
    for i in range(10):
        r = 7 if i % 2 == 0 else 3
        a = -math.pi / 2 + i * math.pi / 5
        pts.append((8 + r * math.cos(a), 8.5 + r * math.sin(a)))
    for x in range(16):
        for y in range(16):
            px, py, inside = x + 0.5, y + 0.5, False
            j = len(pts) - 1
            for i in range(len(pts)):
                xi, yi = pts[i]
                xj, yj = pts[j]
                if (yi > py) != (yj > py) and px < (xj - xi) * (py - yi) / (yj - yi) + xi:
                    inside = not inside
                j = i
            if inside:
                grid.put(x, y, 'A')


def book(grid, enchanted=False):
    for x in range(3, 13):
        for y in range(2, 14):
            grid.put(x, y, 'M')
    for y in range(2, 14):
        grid.put(3, y, 'H')
    for x in range(4, 13):
        grid.put(x, 13, 'W')
    if enchanted:
        for x, y in ((6, 5), (9, 5), (7, 8), (10, 8), (8, 10)):
            grid.put(x, y, 'G')
    else:
        for x in range(6, 11):
            grid.put(x, 5, 'A')


def scroll(grid):
    for x in range(3, 13):
        for y in range(3, 13):
            grid.put(x, y, 'W')
    for x in range(2, 14):
        grid.put(x, 2, 'H')
        grid.put(x, 13, 'H')
    for y in (5, 7, 9):
        for x in range(5, 11 if y != 9 else 9):
            grid.put(x, y, 'I')


def flame(grid):
    grid.rows(['.......F', '.......F', '......FF', '......FF', '.....FFF', '....FFFF', '...FFFFF', '...FFFYY',
               '..FFFYYY', '..FFYYYY', '..FFYYYW', '...FYYYW', '....FYYY'], {'F': 'F', 'Y': 'Y', 'W': 'W'}, oy=2, mirror=True)


def bolt(grid):
    grid.rows(['.........AAAA...', '........AAAA....', '.......AAAA.....', '......AAAA......', '.....AAAAAAAA...',
               '.....AAAAAAA....', '.........AAA....', '........AAA.....', '.......AAA......', '......AA........',
               '.....AA.........', '....A...........'], {'A': 'A'}, oy=2)


def bow(grid):
    for x in range(16):
        for y in range(16):
            r = math.hypot(x + 0.5 - 2, y + 0.5 - 14)
            if 11 <= r <= 12.6 and x >= 2 and y <= 14:
                grid.put(x, y, 'H')
    for x in range(3, 14):
        grid.put(x, 16 - x, 'W')


# ---------- rank tags (48 x 48 with a 5 x 7 pixel font) ----------
FONT = {
    'V': ['10001', '10001', '10001', '10001', '10001', '01010', '00100'], 'I': ['111', '010', '010', '010', '010', '010', '111'],
    'P': ['11110', '10001', '10001', '11110', '10000', '10000', '10000'], '+': ['00000', '00100', '00100', '11111', '00100', '00100', '00000'],
    'M': ['10001', '11011', '10101', '10101', '10001', '10001', '10001'], 'E': ['11111', '10000', '10000', '11110', '10000', '10000', '11111'],
    'L': ['10000', '10000', '10000', '10000', '10000', '10000', '11111'], 'T': ['11111', '00100', '00100', '00100', '00100', '00100', '00100'],
    'G': ['01111', '10000', '10000', '10011', '10001', '10001', '01111'], 'N': ['10001', '11001', '10101', '10011', '10001', '10001', '10001'],
    'D': ['11110', '10001', '10001', '10001', '10001', '10001', '11110'], 'S': ['01111', '10000', '10000', '01110', '00001', '00001', '11110'],
    'O': ['01110', '10001', '10001', '10001', '10001', '10001', '01110'], 'R': ['11110', '10001', '10001', '11110', '10100', '10010', '10001'],
    'H': ['10001', '10001', '10001', '11111', '10001', '10001', '10001'], 'K': ['10001', '10010', '10100', '11000', '10100', '10010', '10001'],
}


def rank_tag(grid, label):
    left, right, top, bottom = 1, 46, 17, 42
    for x in range(left, right + 1):
        for y in range(top, bottom + 1):
            if (x in (left, right)) and (y in (top, bottom)):
                continue
            stripe = (x - y) % 16 in (0, 1, 2) and top + 2 < y < bottom - 1 and not (label and 24 <= y <= 34)
            grid.put(x, y, 'L' if stripe else 'M')
    for x in range(left + 2, right - 1):
        grid.put(x, top + 2, 'L')
        grid.put(x, bottom - 2, 'D')
    for x, y in ((left + 2, top + 4), (right - 2, top + 4), (left + 2, bottom - 4), (right - 2, bottom - 4)):
        grid.put(x, y, 'D')
    for x in range(48):
        for y in range(2, top):
            r = math.hypot(x + 0.5 - 24, y + 0.5 - 18)
            if 11 <= r <= 14:
                grid.put(x, y, 'H')
    if label:
        widths = [len(FONT[ch][0]) for ch in label]
        width = sum(widths) + len(label) - 1
        x0, y0 = 24 - width // 2, 26
        for ch, w in zip(label, widths):
            for gy, row in enumerate(FONT[ch]):
                for gx, bit in enumerate(row):
                    if bit == '1':
                        grid.put(x0 + gx + 1, y0 + gy + 1, 'D')
            for gy, row in enumerate(FONT[ch]):
                for gx, bit in enumerate(row):
                    if bit == '1':
                        grid.put(x0 + gx, y0 + gy, 'T')
            x0 += w + 1


TIERS = [('wood', 'Wooden'), ('stone', 'Stone'), ('iron', 'Iron'), ('gold', 'Golden'), ('diamond', 'Diamond'), ('obsidian', 'Obsidian')]
ARMOR_TIERS = [('leather', 'Leather'), ('iron', 'Iron'), ('gold', 'Golden'), ('diamond', 'Diamond'), ('obsidian', 'Obsidian'), ('copper', 'Copper')]
CRATE_TIERS = [('wood', 'Common'), ('diamond', 'Rare'), ('amethyst', 'Epic'), ('gold', 'Legendary'), ('ruby', 'Mythic'), ('emerald', 'Vote')]
COLOURS = [('green', 'Green', '#5fae3c', '#2f5e1d'), ('red', 'Red', '#c8323d', '#5a1218'), ('blue', 'Blue', '#3567d6', '#122a66'),
           ('purple', 'Purple', '#8a4bc8', '#3a1a5e'), ('orange', 'Orange', '#e8862e', '#6a3410'), ('yellow', 'Yellow', '#e8c43a', '#7a5d10'),
           ('cyan', 'Cyan', '#2fb8c0', '#0d4a50'), ('pink', 'Pink', '#ef8fb8', '#a83d6c'), ('white', 'White', '#e8e8e8', '#8a8a8a'),
           ('black', 'Black', '#3a3a42', '#121216'), ('brown', 'Brown', '#8a5a32', '#3e2612'), ('lime', 'Lime', '#9bd63c', '#4a7a12'),
           ('teal', 'Teal', '#2a9a86', '#e8f4ee'), ('grey', 'Grey', '#8a8a94', '#cfd3da'), ('gold', 'Gold', '#f0b52a', '#8a5a0a'),
           ('violet', 'Violet', '#5b4a78', '#d9c8ff')]
RANKS = [('vip', 'VIP', 'iron'), ('vip-plus', 'VIP+', 'gold'), ('mvp', 'MVP', 'diamond'), ('mvp-plus', 'MVP+', 'obsidian'),
         ('elite', 'ELITE', 'emerald'), ('legend', 'LEGEND', 'ruby'), ('sponsor', 'SPONSOR', 'amethyst'), ('hero', 'HERO', 'lapis'),
         ('king', 'KING', 'gold')]
RANK_COLOURS = [('iron', 'Silver'), ('gold', 'Gold'), ('diamond', 'Cyan'), ('obsidian', 'Dark'), ('emerald', 'Green'),
                ('ruby', 'Red'), ('amethyst', 'Purple'), ('lapis', 'Blue'), ('copper', 'Copper')]


def build():
    icons = {}  # (pack, category) -> list of (slug, name, svg)

    def add(pack, category, slug, name, grid, palettes, flat=()):
        icons.setdefault((pack, category), []).append((slug, name, grid.svg(palettes, flat)))

    for slug, label, colour in RANKS:
        g = Grid(48)
        rank_tag(g, label)
        add('ranks', 'ranks', slug, label, g, palettes_for(M=colour, L=tone(MATERIALS[colour], 0.3), H='leather', T='white', D=tone(MATERIALS[colour], -0.45)), flat=('T', 'D', 'L'))
    for colour, name in RANK_COLOURS:
        g = Grid(48)
        rank_tag(g, '')
        add('ranks', 'blank-tags', 'tag-' + colour, name + ' tag', g, palettes_for(M=colour, L=tone(MATERIALS[colour], 0.3), H='leather', D=tone(MATERIALS[colour], -0.45)), flat=('L', 'D'))

    shaped = [('swords', sword, 'sword'), ('pickaxes', pickaxe, 'pickaxe'), ('axes', axe, 'axe')]
    for category, painter, noun in shaped:
        for material, adjective in TIERS:
            g = Grid()
            painter(g)
            add('items', category, material + '-' + noun, adjective + ' ' + noun, g, palettes_for(M=material, H='wood' if material != 'wood' else '#6b4526', A='gold' if material != 'gold' else 'obsidian'))
    for material, adjective in TIERS:
        for painter, noun in ((shovel, 'shovel'), (hoe, 'hoe')):
            g = Grid()
            painter(g)
            add('items', 'tools', material + '-' + noun, adjective + ' ' + noun, g, palettes_for(M=material, H='wood' if material != 'wood' else '#6b4526'))
    g = Grid()
    bow(g)
    add('items', 'tools', 'bow', 'Bow', g, palettes_for(H='wood', W='white'))

    for material, adjective in ARMOR_TIERS:
        for rows, noun, oy in ((HELMET, 'helmet', 1), (CHEST, 'chestplate', 0), (LEGS, 'leggings', 0), (BOOTS, 'boots', 0)):
            g = Grid()
            mirrored(g, rows, {'M': 'M'}, oy)
            add('items', 'armor', material + '-' + noun, adjective + ' ' + noun, g, palettes_for(M=material))

    for material, tier in CRATE_TIERS:
        g = Grid()
        key(g)
        add('items', 'keys', material + '-key', tier + ' key', g, palettes_for(M=material))
        g = Grid()
        mirrored(g, CRATE, {'M': 'M', 'A': 'A'})
        g.put(7, 7, 'L'); g.put(8, 7, 'L'); g.put(7, 8, 'L'); g.put(8, 8, 'L')
        add('items', 'crates', material + '-crate', tier + ' crate', g, palettes_for(M=material, A='iron' if material != 'iron' else 'gold', L='gold' if material != 'gold' else 'obsidian'))

    for slug, name, base, spot in COLOURS[:10]:
        g = Grid()
        spawner(g)
        add('items', 'spawners', slug + '-spawner', name + ' spawner', g, palettes_for(K='cage', B=base, F='flame'))
    for slug, name, base, spot in COLOURS:
        g = Grid()
        egg(g, EGG_SPOTS)
        add('items', 'eggs', slug + '-egg', name + ' egg', g, palettes_for(B=base, S=spot), flat=('S',))

    g = Grid(); coin(g); add('items', 'economy', 'coin', 'Coin', g, palettes_for(A='gold', M='#b07812'), flat=('M',))
    g = Grid(); coins(g); add('items', 'economy', 'coin-stack', 'Coin stack', g, palettes_for(A='gold', M='#e0a020'))
    g = Grid(); bar(g); add('items', 'economy', 'gold-bar', 'Gold bar', g, palettes_for(A='gold', M='#c98a16'))
    g = Grid(); note(g); add('items', 'economy', 'banknote', 'Banknote', g, palettes_for(G='green', L='#2f5e1d'))
    g = Grid(); g.rows(BAG, {'L': 'L', 'A': 'A'}, mirror=True); add('items', 'economy', 'money-bag', 'Money bag', g, palettes_for(L='leather', A='gold'))
    for material, name in (('emerald', 'Emerald'), ('diamond', 'Diamond'), ('ruby', 'Ruby'), ('lapis', 'Sapphire'), ('amethyst', 'Amethyst'), ('gold', 'Topaz')):
        g = Grid(); gem(g); add('items', 'economy', material + '-gem', name, g, palettes_for(G=material, L=tone(MATERIALS[material], 0.45)), flat=('L',))

    for slug, name, base, spot in COLOURS[:8]:
        g = Grid()
        mirrored(g, POTION, {'W': 'W', 'G': 'G', 'L': 'L'})
        add('items', 'potions', slug + '-potion', name + ' potion', g, palettes_for(W='#c8a070', G='#cfe8f0', L=base))

    misc = [
        ('crown', 'Crown', lambda g: mirrored(g, CROWN, {'A': 'A', 'G': 'G', 'M': 'M'}, 1), palettes_for(A='gold', G='ruby', M='#b07812')),
        ('trophy', 'Trophy', lambda g: mirrored(g, TROPHY, {'A': 'A', 'M': 'M'}, 1), palettes_for(A='gold', M='stone')),
        ('shield', 'Shield', lambda g: mirrored(g, SHIELD, {'A': 'A', 'M': 'M'}, 1), palettes_for(M='lapis', A='gold')),
        ('heart', 'Heart', lambda g: mirrored(g, HEART, {'M': 'M'}), palettes_for(M='ruby')),
        ('gift', 'Gift', lambda g: mirrored(g, GIFT, {'A': 'A', 'M': 'M'}), palettes_for(M='ruby', A='gold')),
        ('bell', 'Bell', lambda g: mirrored(g, BELL, {'A': 'A', 'M': 'M'}), palettes_for(A='gold', M='#b07812')),
        ('star', 'Star', star, palettes_for(A='gold')),
        ('book', 'Book', book, palettes_for(M='ruby', H='#5a1218', W='paper', A='gold')),
        ('enchanted-book', 'Enchanted book', lambda g: book(g, True), palettes_for(M='amethyst', H='#3a1a5e', W='paper', G='#ffe3ff')),
        ('scroll', 'Scroll', scroll, palettes_for(W='paper', H='wood', I='ink'), ),
        ('flame', 'Flame', flame, palettes_for(F='#e8532a', Y='flame', W='#fff1a0')),
        ('lightning', 'Lightning', bolt, palettes_for(A='#f7d046')),
    ]
    for slug, name, painter, palettes in misc:
        g = Grid()
        painter(g)
        add('items', 'misc', slug, name, g, palettes, flat=('I',))
    return icons


PACKS = {'ranks': 'Rank tags', 'items': 'Voxel items'}
CATEGORIES = {'ranks': 'Ranks', 'blank-tags': 'Blank tags', 'swords': 'Swords', 'axes': 'Axes', 'pickaxes': 'Pickaxes',
              'tools': 'Tools', 'armor': 'Armor', 'keys': 'Keys', 'crates': 'Crates', 'spawners': 'Spawners', 'eggs': 'Spawn eggs',
              'economy': 'Economy', 'potions': 'Potions', 'misc': 'Misc'}


def main():
    icons = build()
    if OUT.exists():
        for old in OUT.glob('*/*.svg'):
            old.unlink()
    manifest = {'format': 1, 'packs': []}
    for pack, pack_name in PACKS.items():
        entry = {'id': pack, 'name': pack_name, 'categories': []}
        for (p, category), items in icons.items():
            if p != pack:
                continue
            folder = OUT / category
            folder.mkdir(parents=True, exist_ok=True)
            rows = []
            for slug, name, svg in items:
                (folder / (slug + '.svg')).write_text(svg, encoding='utf-8', newline='\n')
                rows.append({'slug': slug, 'name': name, 'path': 'library/%s/%s.svg' % (category, slug)})
            entry['categories'].append({'id': category, 'name': CATEGORIES[category], 'icons': rows})
        manifest['packs'].append(entry)
    MANIFEST.write_text(json.dumps(manifest, indent=1) + '\n', encoding='utf-8', newline='\n')
    total = sum(len(v) for v in icons.values())
    print('icons', total)
    if '--preview' in sys.argv:
        preview(icons, sys.argv[sys.argv.index('--preview') + 1])


def preview(icons, target):
    import re
    from PIL import Image, ImageDraw
    flat = [svg for items in icons.values() for _, _, svg in items]
    cols, cell = 14, 72
    sheet = Image.new('RGB', (cols * cell, math.ceil(len(flat) / cols) * cell), '#1b1f26')
    draw = ImageDraw.Draw(sheet)
    for i, svg in enumerate(flat):
        size = int(re.search(r'viewBox="0 0 (\d+)', svg).group(1))
        scale = 64 / size
        ox, oy = (i % cols) * cell + 4, (i // cols) * cell + 4
        for colour, d in re.findall(r'fill="(#[0-9a-f]+)" d="([^"]+)"', svg):
            for x, y, w in re.findall(r'M(\d+) (\d+)h(\d+)', d):
                x, y, w = int(x), int(y), int(w)
                draw.rectangle([ox + round(x * scale), oy + round(y * scale), ox + round((x + w) * scale) - 1, oy + round((y + 1) * scale) - 1], fill=colour[:7])
    sheet.save(target)


if __name__ == '__main__':
    main()
