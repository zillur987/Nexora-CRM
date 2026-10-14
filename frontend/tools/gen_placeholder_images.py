"""Procedural architectural placeholder art (no photos). Replace with real photography before launch."""
import numpy as np, random, math, sys, os
from PIL import Image, ImageDraw, ImageFilter

OUT = sys.argv[1]
PAL = {
 'dusk':  dict(top=(26,30,66),  mid=(142,92,112), hor=(246,172,112), sun=(255,196,130)),
 'teal':  dict(top=(12,38,58),  mid=(62,122,132), hor=(242,204,152), sun=(255,222,170)),
 'night': dict(top=(8,12,32),   mid=(44,56,108),  hor=(214,132,122), sun=(255,170,140)),
 'sage':  dict(top=(58,88,112), mid=(168,190,182),hor=(250,228,194), sun=(255,238,200)),
 'amber': dict(top=(38,22,30),  mid=(152,72,60),  hor=(252,172,84),  sun=(255,190,100)),
}
def mix(a, b, t): return tuple(int(a[i] + (b[i] - a[i]) * t) for i in range(3))

def sky(w, h, p, hz):
    y = np.linspace(0, 1, h)
    xs = [0, hz * .5, hz, 1]
    ch = [np.interp(y, xs, [p['top'][c], p['mid'][c], p['hor'][c], p['hor'][c]]) for c in range(3)]
    img = np.stack([np.tile(c[:, None], (1, w)) for c in ch], -1)
    yy, xx = np.mgrid[0:h, 0:w]
    sx = w * random.uniform(.3, .7)
    d = np.sqrt(((xx - sx) / (w * .55)) ** 2 + ((yy - hz * h) / (h * .42)) ** 2)
    glow = np.clip(1 - d, 0, 1) ** 2.2
    for c in range(3): img[..., c] += glow * p['sun'][c] * .55
    return Image.fromarray(np.clip(img, 0, 255).astype('uint8')).convert('RGBA')

def towers(im, base, n, hmin, hmax, col, lit=.35, blur=0, wmin=None, wmax=None):
    w = im.width; L = Image.new('RGBA', im.size, (0, 0, 0, 0)); d = ImageDraw.Draw(L)
    wmin = wmin or w // (n + 2); wmax = wmax or int(w / n * 1.9); x = -wmin // 2
    while x < w:
        tw = random.randint(wmin, wmax); th = random.randint(hmin, hmax); top = base - th
        d.rectangle([x, top, x + tw, base + 400], fill=col + (255,))
        d.rectangle([x, top, x + max(2, tw // 14), base + 400], fill=mix(col, (255, 255, 255), .10) + (255,))
        if random.random() < .4: d.rectangle([x + tw // 3, top - random.randint(10, 60), x + tw // 3 + 3, top], fill=col + (255,))
        if lit:
            cw, chh = 9, 15
            for wx in range(x + 8, x + tw - 8, cw + 5):
                for wy in range(top + 14, base - 6, chh + 7):
                    if random.random() < lit:
                        a = random.randint(150, 255)
                        d.rectangle([wx, wy, wx + cw, wy + chh], fill=(255, random.randint(196, 226), random.randint(130, 170), a))
        x += tw + random.randint(0, 14)
    if blur: L = L.filter(ImageFilter.GaussianBlur(blur))
    im.alpha_composite(L)

def water(im, hzpx, p):
    w, h = im.size; top = im.crop((0, 0, w, hzpx)).transpose(Image.FLIP_TOP_BOTTOM)
    a = np.asarray(top.resize((w, h - hzpx))).astype('float32')
    rows = np.arange(a.shape[0])
    for r in rows: a[r] = np.roll(a[r], int(math.sin(r / 7.0) * (2 + r / 55)), axis=0)
    a[..., :3] = a[..., :3] * .55 + np.array(mix(p['top'], (0, 0, 0), .5)) * .45
    wt = Image.fromarray(a.astype('uint8')).filter(ImageFilter.GaussianBlur(2.2))
    im.paste(wt, (0, hzpx))

def ridges(im, hz, layers, p, trees=False):
    w, h = im.size
    for i in range(layers):
        t = i / max(1, layers - 1); col = mix(p['hor'], (10, 14, 16), .35 + .6 * t)
        base = hz + (h - hz) * (.05 + .55 * t); amp = (h - hz) * (.30 - .12 * t)
        ph = [random.uniform(0, 6.28) for _ in range(3)]
        xs = np.arange(w); y = base - amp * (.5 + .3 * np.sin(xs / (w / (2.2 + i)) + ph[0]) + .2 * np.sin(xs / (w / (6 + i * 2)) + ph[1]))
        L = Image.new('RGBA', im.size, (0, 0, 0, 0)); d = ImageDraw.Draw(L)
        d.polygon([(0, h)] + [(int(x), int(v)) for x, v in zip(xs, y)] + [(w, h)], fill=col + (255,))
        if trees and i >= layers - 2:
            for x in range(0, w, random.randint(14, 26)):
                ty = int(y[min(x, w - 1)]); th = random.randint(40, 110)
                d.polygon([(x, ty + 6), (x - th // 5, ty + 6), (x, ty - th), (x + th // 5, ty + 6)], fill=mix(col, (0, 0, 0), .35) + (255,))
        L = L.filter(ImageFilter.GaussianBlur(1.2 + (layers - i) * .5 if i < layers - 1 else 0))
        im.alpha_composite(L)
        mist = Image.new('RGBA', im.size, p['hor'] + (0,)); md = ImageDraw.Draw(mist)
        for k in range(40): md.rectangle([0, int(base - 60 + k * 2), w, int(base - 58 + k * 2)], fill=p['hor'] + (int(3 + 2 * math.sin(k / 6)),))
        im.alpha_composite(mist.filter(ImageFilter.GaussianBlur(14)))

def villa(im, gy, p, big=True):
    w, h = im.size; L = Image.new('RGBA', im.size, (0, 0, 0, 0)); d = ImageDraw.Draw(L)
    d.rectangle([0, gy, w, h], fill=(14, 16, 18, 255))
    bx, bw = (int(w * .40), int(w * .56)) if big else (int(w * .12), int(w * .76)); bh = int(h * (.24 if big else .17)); by = gy - bh
    d.rectangle([bx, by, bx + bw, gy], fill=(30, 32, 34, 255))
    d.rectangle([bx - 30, by - 14, bx + bw + 30, by], fill=(236, 232, 224, 255))
    gl = np.zeros((bh - 10, int(bw * .62), 4), 'float32')
    for r in range(gl.shape[0]): gl[r, :, :3] = mix((255, 214, 150), (255, 150, 90), r / gl.shape[0]); gl[r, :, 3] = 255
    L.paste(Image.fromarray(gl.astype('uint8')), (bx + int(bw * .06), by + 5))
    fx = bx + int(bw * .72)
    d.rectangle([fx, by - int(bh * .5), fx + int(bw * .24), gy], fill=(52, 48, 44, 255))
    d.rectangle([fx - 10, by - int(bh * .5) - 12, fx + int(bw * .24) + 10, by - int(bh * .5)], fill=(226, 222, 214, 255))
    for k in range(3): d.rectangle([fx + 14 + k * int(bw * .07), by - int(bh * .5) + 16, fx + 14 + k * int(bw * .07) + int(bw * .045), gy - 12], fill=(255, 196, 130, 235))
    py = gy + int((h - gy) * .12); d.rectangle([bx - 20, py, bx + bw + 20, py + int((h - gy) * .38)], fill=mix(p['mid'], p['hor'], .5) + (255,))
    for _ in range(4 if big else 2):
        tx = random.choice([int(w * .06), int(w * .93), int(w * .02), int(w * .97)]); ty = gy - random.randint(int(h * .22), int(h * .34))
        d.line([(tx, gy), (tx + 8, ty)], fill=(8, 10, 10, 255), width=6)
        for a in range(-80, 90, 22):
            ex = tx + 8 + math.sin(math.radians(a)) * h * .12; ey = ty + 20 - math.cos(math.radians(a)) * h * .05 + abs(a) * .5
            d.line([(tx + 8, ty), (ex, ey)], fill=(8, 10, 10, 255), width=4)
    im.alpha_composite(L)

def garden(im, gy, p):
    w, h = im.size; L = Image.new('RGBA', im.size, (0, 0, 0, 0)); d = ImageDraw.Draw(L)
    d.rectangle([0, gy, w, h], fill=(18, 24, 18, 255)); n = 5; hw = w // n
    pal = [(208, 188, 160), (176, 150, 124), (226, 214, 196), (150, 128, 108), (196, 170, 140)]
    for i in range(n):
        x = i * hw; hh = int(h * random.uniform(.2, .27)); top = gy - hh
        d.rectangle([x + 6, top, x + hw - 6, gy], fill=pal[i % 5] + (255,))
        d.polygon([(x, top), (x + hw // 2, top - hh // 3), (x + hw, top)], fill=(44, 40, 38, 255))
        for r in range(2):
            for c in range(2):
                wx = x + hw // 6 + c * hw // 2.6; wy = top + 20 + r * hh // 2.3
                d.rectangle([wx, wy, wx + hw // 5, wy + hh // 3.4], fill=(255, random.randint(190, 220), 140, 235))
    for _ in range(14):
        tx = random.randint(0, w); tr = random.randint(int(h * .04), int(h * .09))
        d.ellipse([tx - tr, gy - tr * 1.8, tx + tr, gy + tr * .2], fill=(14, 40, 26, 255))
    d.polygon([(w * .42, h), (w * .58, h), (w * .53, gy + 14), (w * .47, gy + 14)], fill=mix(p['hor'], (40, 30, 24), .5) + (255,))
    im.alpha_composite(L)

def finish(im):
    w, h = im.size; a = np.asarray(im.convert('RGB')).astype('float32')
    yy, xx = np.mgrid[0:h, 0:w]; v = 1 - .34 * (((xx - w / 2) / (w / 1.5)) ** 2 + ((yy - h / 2) / (h / 1.5)) ** 2)
    a = a * v[..., None] + np.random.normal(0, 4.5, (h, w, 1))
    return Image.fromarray(np.clip(a, 0, 255).astype('uint8'))

def make(name, theme, pal, w, h, seed):
    random.seed(seed); np.random.seed(seed); p = PAL[pal]
    hz = {'towers': .70, 'water': .60, 'villa': .66, 'hills': .52, 'garden': .68}[theme]; hzpx = int(h * hz)
    im = sky(w, h, p, hz)
    if theme in ('towers', 'water'):
        far = mix(p['hor'], p['mid'], .55); mid = mix(p['mid'], (14, 16, 30), .55)
        towers(im, hzpx, 16, int(h * .08), int(h * .22), far, lit=0, blur=2.5)
        towers(im, hzpx, 9, int(h * .12), int(h * .36), mid, lit=.18, blur=.8)
        if theme == 'towers':
            towers(im, hzpx + int(h * .18), 5, int(h * .25), int(h * .5), (12, 14, 20), lit=.30, wmin=w // 6, wmax=w // 3)
            ImageDraw.Draw(im).rectangle([0, hzpx + int(h * .18), w, h], fill=(10, 12, 16, 255))
        else:
            ImageDraw.Draw(im).rectangle([0, hzpx, w, hzpx + 3], fill=(20, 20, 24, 255)); water(im, hzpx, p)
    elif theme == 'villa': ridges(im, hzpx, 2, p); villa(im, hzpx + int(h * .06), p, w > h * 1.2)
    elif theme == 'hills': ridges(im, hzpx, 5, p, trees=True)
    elif theme == 'garden': ridges(im, hzpx - int(h * .03), 2, p); garden(im, hzpx + int(h * .04), p)
    os.makedirs(OUT, exist_ok=True); finish(im).save(f'{OUT}/{name}.jpg', quality=80, optimize=True, progressive=True)

SPEC = [
 # name, theme, palette, w, h
 ('community-marina-bay','water','teal',1400,1480), ('community-palm-grove','villa','amber',1400,1480),
 ('community-the-meadows','garden','sage',1400,1480), ('community-skyline-district','towers','night',1400,1480),
 ('community-cedar-ridge','hills','dusk',1400,1480),
 ('type-apartments','towers','dusk',1100,1500), ('type-townhouses','garden','amber',1100,1500),
 ('type-villas','villa','teal',1100,1500), ('type-penthouses','towers','night',1100,1500),
 ('why-aurelia','villa','dusk',1200,1500), ('post-1','water','dusk',1400,1100), ('post-2','garden','sage',900,900), ('post-3','villa','amber',900,900),
 ('harbour-point','water','dusk',1200,1440), ('the-meadows','garden','sage',1200,1440), ('palm-grove','villa','teal',1200,1440),
 ('skyline-district','towers','night',1200,1440), ('marina-walk','water','amber',1200,1440), ('cedar-hills','hills','teal',1200,1440),
 ('spot-1','villa','dusk',1920,1080), ('spot-2','towers','night',1920,1080), ('spot-3','water','amber',1920,1080), ('spot-4','hills','sage',1920,1080),
 ('gal-1','villa','teal',1000,1300), ('gal-2','towers','dusk',1000,800), ('gal-3','garden','amber',1000,1000), ('gal-4','water','night',1000,1300),
 ('gal-5','hills','dusk',1000,800), ('gal-6','villa','sage',1000,1000),
]
for i, (n, t, pl, w, h) in enumerate(SPEC): make(n, t, pl, w, h, 100 + i)
print(len(SPEC), 'images')
