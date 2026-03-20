# Architecture: mpdf

## Purpose

A PHP library for generating PDF files from HTML and CSS. Converts HTML markup with CSS styling into PDF documents, supporting Unicode, RTL languages, CJK fonts, barcodes, watermarks, headers/footers, and table of contents.

## Directory Structure

```
src/
  Mpdf.php                — Monolithic core: HTML parsing, CSS application, layout, PDF output
  Css/
    Css_Parser.php        — Parses CSS (selectors, properties, media queries)
    Css_Loader.php        — Loads CSS from <link>, <style>, and inline style attributes
    Selector_Parser.php   — Parses CSS selector specificity
    Border_Merger.php     — Merges border shorthand properties
    ...
  Fonts/
    Font_Cache.php        — Caches processed font metrics
    Font_File_Finder.php  — Discovers font files by name and path
    Glyph_Operator.php    — Glyph lookup and shaping
    Metrics_Generator.php — Reads TrueType/OpenType font metrics
  Barcode/               — Barcode generators: Code128, Code39, EAN, QR, etc.
  Color/
    Color_Converter.php   — Converts between RGB, CMYK, HSL
    Named_Colors.php      — CSS named color lookup table
  Image/
    Image_Processor.php   — Image embedding: JPEG, PNG, GIF, BMP, WMF, SVG
    Svg.php               — SVG-to-PDF renderer
  Gif/                   — GIF decoder (animation frame extraction)
  Language/
    Language_To_Font.php  — Maps Unicode language/script to appropriate font
  Otl.php                — OpenType Layout: ligatures, kerning, GSUB/GPOS tables
  Hyphenator.php         — Language-aware hyphenation (Knuth algorithm)
  Output/Destination.php — Output mode enum: INLINE, DOWNLOAD, STRING, FILE
  Config/
    Config_Variables.php  — Default configuration values
    Font_Variables.php    — Font configuration defaults
  Exception/             — Typed exceptions
```

## Key Design Decisions

- **Monolithic Mpdf class**: The core `Mpdf.php` is very large (10,000+ lines); it handles HTML tokenization, CSS cascade, box model layout, and PDF stream generation in one class — a known architectural debt
- **HTML-to-PDF pipeline**: HTML is parsed incrementally, CSS is applied per element, and layout is performed in a single forward pass (no reflow); this limits support for complex CSS layouts
- **Font subsetting**: Only the glyph codes actually used in the document are embedded in the PDF, reducing file size
- **OTL (OpenType Layout)**: Arabic, Hebrew, Indic script shaping is handled via `Otl.php` which reads GSUB/GPOS font tables

## Extension Points

- Provide a custom `AssetFetcherInterface` to control how remote assets (images, fonts) are fetched
- Provide a custom `LocalContentLoaderInterface` to control how local file paths are resolved
- Use `$mpdf->SetHTMLHeader()` / `$mpdf->SetHTMLFooter()` for page headers and footers

## Dependency Flow

```
new Mpdf($config)
  → WriteHTML($html)       — parse HTML, apply CSS, lay out content
  → Output($dest, $mode)   — serialize to PDF binary
      → Font subsetting
      → Image embedding
      → Cross-reference table
```
