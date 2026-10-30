# Bundled typefaces

The original, unmodified variable font files are served locally. No font request is sent to an external CDN. All four typefaces use the SIL Open Font License 1.1; the complete copyright notices and licence text accompany each family.

| File | Family | Original source |
| --- | --- | --- |
| `manrope.ttf` | Manrope | [Google Fonts](https://github.com/google/fonts/tree/main/ofl/manrope) |
| `bitter.ttf` | Bitter | [Google Fonts](https://github.com/google/fonts/tree/main/ofl/bitter) |
| `archivonarrow.ttf` | Archivo Narrow | [Google Fonts](https://github.com/google/fonts/tree/main/ofl/archivonarrow) |
| `alegreya.ttf` | Alegreya | [Google Fonts](https://github.com/google/fonts/tree/main/ofl/alegreya) |

The exact distributed files are identified in `SHA256SUMS`. Theme pages load the shared body family and at most one additional display family. Font definitions use `font-display: swap` and retain native system fallbacks.
