#!/usr/bin/env python3
"""Builds assets/fonts/*.woff2 from the Google Fonts "latin" WOFF2 files (see assets/fonts/README.txt).

Usage: tools/subset-fonts.py <source dir containing mono400.woff2, mono500.woff2, sansvar.woff2>
Requires: pip install fonttools brotli
"""
import sys
from pathlib import Path

from fontTools import subset
from fontTools.ttLib import TTFont
from fontTools.varLib import instancer

UNICODES = list(range(0x20, 0x7F)) + [
    0xA0, 0xAB, 0xB7, 0xBB,
    0x2013, 0x2014, 0x2018, 0x2019, 0x201C, 0x201D, 0x2022, 0x2026, 0x2039, 0x203A, 0x2212, 0xFEFF, 0xFFFD,
]


def build(font: TTFont, out: Path) -> None:
    options = subset.Options()
    options.flavor = 'woff2'
    options.layout_features = ['*']
    options.name_IDs = [0, 1, 2, 3, 4, 5, 6, 13, 14]
    options.notdef_outline = True
    options.desubroutinize = True
    subsetter = subset.Subsetter(options)
    subsetter.populate(unicodes=UNICODES)
    subsetter.subset(font)
    font.flavor = 'woff2'
    font.save(out)


def main(src: Path) -> None:
    out = Path(__file__).resolve().parent.parent / 'assets' / 'fonts'
    jobs = [
        ('ibm-plex-mono-400', 'mono400.woff2', None),
        ('ibm-plex-mono-500', 'mono500.woff2', None),
        ('ibm-plex-sans-400', 'sansvar.woff2', 400),
        ('ibm-plex-sans-500', 'sansvar.woff2', 500),
    ]
    for name, file, weight in jobs:
        font = TTFont(src / file)
        if weight is not None:
            font = instancer.instantiateVariableFont(font, {'wght': weight}, inplace=False)
        build(font, out / f'{name}.woff2')


if __name__ == '__main__':
    main(Path(sys.argv[1]))
