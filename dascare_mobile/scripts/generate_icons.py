#!/usr/bin/env python3
"""Generate the Android app icon + splash images from the DASCARE logo.

Run from dascare_mobile/:  npm run assets   (or: python3 scripts/generate_icons.py)

Source: assets/logo.png (the web's dascare/img/logoo.png on a square canvas).
Overwrites the PNGs Capacitor generated under android/app/src/main/res,
keeping each file's existing pixel size, so it is safe to re-run.

Colours match the web theme (dascare/src/assets/main.css):
  icon tile  #ffffff  (the white logo tile in the web header)
  splash     #f5efe1  light base-200  /  #0b1826 dark base-200
"""
from pathlib import Path
from PIL import Image, ImageDraw

ROOT = Path(__file__).resolve().parent.parent
RES = ROOT / 'android' / 'app' / 'src' / 'main' / 'res'
LOGO = Image.open(ROOT / 'assets' / 'logo.png').convert('RGBA')
LOGO = LOGO.crop(LOGO.getbbox())

ICON_BG = (255, 255, 255, 255)
SPLASH_LIGHT = (0xF5, 0xEF, 0xE1, 255)
SPLASH_DARK = (0x0B, 0x18, 0x26, 255)


def fit_logo(box: int) -> Image.Image:
    w, h = LOGO.size
    scale = box / max(w, h)
    return LOGO.resize((max(1, round(w * scale)), max(1, round(h * scale))), Image.LANCZOS)


def centered(canvas: Image.Image, logo: Image.Image) -> Image.Image:
    canvas.paste(logo, ((canvas.width - logo.width) // 2, (canvas.height - logo.height) // 2), logo)
    return canvas


def foreground(size: int) -> Image.Image:
    # Adaptive icon: 108dp canvas, launcher masks to the inner ~66dp, so
    # keep the logo well inside that safe zone.
    return centered(Image.new('RGBA', (size, size), (0, 0, 0, 0)), fit_logo(round(size * 0.56)))


def legacy(size: int, round_mask: bool) -> Image.Image:
    tile = Image.new('RGBA', (size, size), (0, 0, 0, 0))
    mask = Image.new('L', (size, size), 0)
    draw = ImageDraw.Draw(mask)
    if round_mask:
        draw.ellipse((0, 0, size - 1, size - 1), fill=255)
    else:
        draw.rounded_rectangle((0, 0, size - 1, size - 1), radius=round(size * 0.22), fill=255)
    tile.paste(Image.new('RGBA', (size, size), ICON_BG), (0, 0), mask)
    return centered(tile, fit_logo(round(size * 0.68)))


def splash(w: int, h: int, bg) -> Image.Image:
    return centered(Image.new('RGBA', (w, h), bg), fit_logo(round(min(w, h) * 0.32)))


def main() -> None:
    count = 0
    for png in sorted(RES.rglob('*.png')):
        with Image.open(png) as existing:
            w, h = existing.size
        name, folder = png.name, png.parent.name
        if name == 'ic_launcher_foreground.png':
            img = foreground(w)
        elif name == 'ic_launcher.png':
            img = legacy(w, round_mask=False)
        elif name == 'ic_launcher_round.png':
            img = legacy(w, round_mask=True)
        elif name == 'splash.png':
            img = splash(w, h, SPLASH_DARK if '-night' in folder else SPLASH_LIGHT)
        else:
            continue
        img.save(png)
        count += 1

    # Dark-mode splash images: mirror every drawable*/splash.png into a
    # matching drawable-night* folder.
    for png in sorted(RES.glob('drawable*/splash.png')):
        if '-night' in png.parent.name:
            continue
        # Android qualifier order: orientation (land/port) before night.
        parts = png.parent.name.split('-')
        at = 2 if len(parts) > 1 and parts[1] in ('land', 'port') else 1
        night_dir = RES / '-'.join(parts[:at] + ['night'] + parts[at:])
        night_dir.mkdir(exist_ok=True)
        with Image.open(png) as existing:
            w, h = existing.size
        splash(w, h, SPLASH_DARK).save(night_dir / 'splash.png')
        count += 1

    # Status-bar notification icon (push): Android draws it as a white
    # silhouette, so use the logo's shape (alpha) in solid white.
    sizes = {'mdpi': 24, 'hdpi': 36, 'xhdpi': 48, 'xxhdpi': 72, 'xxxhdpi': 96}
    alpha = LOGO.split()[-1]
    for density, size in sizes.items():
        mask = alpha.copy()
        mask.thumbnail((round(size * 0.9), round(size * 0.9)), Image.LANCZOS)
        icon = Image.new('RGBA', (size, size), (0, 0, 0, 0))
        white = Image.new('RGBA', mask.size, (255, 255, 255, 255))
        icon.paste(white, ((size - mask.width) // 2, (size - mask.height) // 2), mask)
        out_dir = RES / f'drawable-{density}'
        out_dir.mkdir(exist_ok=True)
        icon.save(out_dir / 'ic_stat_dascare.png')
        count += 1

    print(f'Wrote {count} icon/splash images under {RES.relative_to(ROOT)}')


if __name__ == '__main__':
    main()
