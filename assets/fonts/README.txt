IBM Plex Sans (400, 500) and IBM Plex Mono (400, 500), latin subset, WOFF2.

Licence: SIL Open Font License 1.1, see LICENSE-OFL.txt (copyright IBM Corp.).

Source: the files Google Fonts serves for the "latin" range (IBM Plex Sans v3.201 variable, IBM Plex Mono v2.3).
Plex Sans is a variable font upstream; the two weights used here were instanced as static fonts.
Regenerate with: python3 tools/subset-fonts.py <dir with mono400.woff2 mono500.woff2 sansvar.woff2>
(needs `pip install fonttools brotli`).

Kept glyphs: U+0020-007E, U+00A0, U+00AB, U+00B7, U+00BB, U+2013, U+2014, U+2018, U+2019, U+201C, U+201D,
U+2022, U+2026, U+2039, U+203A, U+2212 and all OpenType features of those glyphs (tabular figures are used).
Other characters fall back to system fonts. Sans 600 is not shipped: no style asks for it.

The files are cached for a year as immutable (assets/.htaccess). Rename a file when its contents change, and update
the @font-face rules in assets/styles/main.css and the preload names in the views.
