#!/usr/bin/env python3
"""One-time reorganization: convert the raw project photo folders under
img/projects/ into clean, SEO-friendly, WebP galleries. Source folders are
removed only after their converted output is verified complete.

Usage: python3 scripts/reorganize-project-images.py
"""
import json
import re
import shutil
from pathlib import Path
from PIL import Image

SITE_ROOT = Path(__file__).resolve().parent.parent
IMG_ROOT = SITE_ROOT / "img" / "projects"

MAX_WIDTH = 1200
QUALITY = 80

# old folder name (relative to img/projects/) -> new slug
FOLDER_MAP = {
    "SHREE BALAJI CGHS": "shree-balaji-cghs",
    "M3M WOODHSIRE": "m3m-woodshire",
    "PURI EMERALD BAY": "puri-emerald-bay",
    "PAREENA HEIGHTS SECOND": "pareena-heights",
    "Pareena Heights  Sector 100": "pareena-heights-sector-100",
    "PROJECT 6  JOYVILLE  SECTOR 102  GURGAON": "joyville-sector-102-gurgaon",
    "SMART WORLD ORCHARD": "smart-world-orchard-sector-61-gurgaon",
}


def natural_key(path: Path):
    """Sort '... 2.jpg' before '... 10.jpg', and 'Bedroom' before 'Kitchen'
    before 'Living room' for the Sector 100 folder's category-labeled files."""
    parts = re.split(r"(\d+)", path.stem)
    return [int(p) if p.isdigit() else p.lower() for p in parts]


def convert(src: Path, dest: Path, max_width=MAX_WIDTH, quality=QUALITY):
    with Image.open(src) as im:
        im = im.convert("RGB")
        if im.width > max_width:
            new_h = round(im.height * max_width / im.width)
            im = im.resize((max_width, new_h), Image.LANCZOS)
        dest.parent.mkdir(parents=True, exist_ok=True)
        im.save(dest, "WEBP", quality=quality, method=6)


def main():
    manifest = {}
    for old_name, slug in FOLDER_MAP.items():
        src_dir = IMG_ROOT / old_name
        if not src_dir.exists():
            print(f"SKIP (not found): {old_name}")
            continue
        images = sorted(
            [p for p in src_dir.rglob("*") if p.suffix.lower() in (".jpg", ".jpeg", ".png")],
            key=natural_key,
        )
        if not images:
            print(f"SKIP (no images): {old_name}")
            continue

        out_dir = IMG_ROOT / slug
        out_dir.mkdir(parents=True, exist_ok=True)

        gallery = []
        for i, img_path in enumerate(images, start=1):
            out_name = f"{slug}-{i:02d}.webp"
            out_path = out_dir / out_name
            convert(img_path, out_path)
            gallery.append(f"/img/projects/{slug}/{out_name}")

        banner_out = out_dir / f"{slug}-banner.webp"
        convert(images[0], banner_out)
        banner_path = f"/img/projects/{slug}/{banner_out.name}"

        produced = list(out_dir.glob(f"{slug}-*.webp"))
        if len(produced) != len(images) + 1:  # +1 for banner
            print(f"MISMATCH for {slug}: {len(images)} source, {len(produced)} produced — NOT removing source")
            continue

        manifest[slug] = {"banner": banner_path, "gallery": gallery, "sourceCount": len(images)}
        shutil.rmtree(src_dir)
        print(f"OK  {old_name}  ->  {slug}/  ({len(images)} images + banner)")

    (SITE_ROOT / "data" / "project-galleries.json").write_text(
        json.dumps(manifest, ensure_ascii=False, indent=2), encoding="utf-8"
    )
    print(f"\nWrote data/project-galleries.json with {len(manifest)} project(s)")


if __name__ == "__main__":
    main()
