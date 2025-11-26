# Translation Files

This directory contains translation files for the AI Hero Assistant plugin.

## Current Translations

- **Romanian (ro_RO)**: `ai-hero-assistant-ro_RO.po`

## Compiling Translation Files

To compile `.po` files into `.mo` files (for better performance), you need the `gettext` package installed.

### On Linux:
```bash
msgfmt ai-hero-assistant-ro_RO.po -o ai-hero-assistant-ro_RO.mo
```

### On macOS:
```bash
brew install gettext
msgfmt ai-hero-assistant-ro_RO.po -o ai-hero-assistant-ro_RO.mo
```

### On Windows:
Install [Poedit](https://poedit.net/) and use it to compile the `.po` files.

## Adding New Translations

1. Copy `ai-hero-assistant-ro_RO.po` to create a new translation file (e.g., `ai-hero-assistant-es_ES.po` for Spanish)
2. Update the header information (Language, Language-Team, etc.)
3. Translate all `msgstr` entries
4. Compile the `.po` file to `.mo` using `msgfmt`

## Note

WordPress can use `.po` files directly, but `.mo` files are recommended for better performance.
