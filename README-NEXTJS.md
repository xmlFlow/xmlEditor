# Next.js Integration with OJS Plugin

This document explains how the Next.js build is integrated into the OJS XML Editor plugin.

## Overview

The Next.js application is built separately and then integrated into the OJS plugin using an iframe. The main challenge is that Next.js generates files with hardcoded absolute paths (`/_next/...`) which need to be converted to relative paths (`../_next/...`) to work correctly within the OJS plugin structure.

## Directory Structure

```
/var/www/html/ojs-3_3/plugins/generic/xmlEditor/
├── editor/                          # Next.js build output
│   ├── editor/                      # Editor page
│   │   └── index.html              # Main HTML (needs path fixing)
│   ├── _next/                       # Next.js static assets
│   │   ├── static/
│   │   │   ├── chunks/             # JavaScript bundles
│   │   │   ├── css/                # CSS files
│   │   │   └── media/              # Fonts and other media
│   └── ...
├── templates/
│   └── editor.tpl                   # OJS template (loads iframe)
└── fix-nextjs-paths.sh             # Path fixing script
```

## How It Works

1. **OJS Template (`templates/editor.tpl`)**
   - Simple HTML wrapper with an iframe
   - Loads the Next.js app from `{$pluginUrl}/editor/editor/`
   - No JavaScript/CSS imports needed in the wrapper

2. **Next.js Build (`editor/editor/index.html`)**
   - Contains all the JavaScript and CSS references
   - Paths are converted from `/_next/` to `../_next/`
   - The `..` resolves to the `editor/` directory where `_next/` lives

3. **Path Resolution**
   ```
   iframe URL: http://localhost/ojs-3_3/plugins/generic/xmlEditor/editor/editor/
   Asset path: ../_next/static/chunks/main.js
   Resolves to: http://localhost/ojs-3_3/plugins/generic/xmlEditor/editor/_next/static/chunks/main.js
   ```

## After Each Next.js Build

Whenever you rebuild your Next.js application and copy it to the `editor/` directory, you **MUST** run the path fixing script:

```bash
cd /var/www/html/ojs-3_3/plugins/generic/xmlEditor
./fix-nextjs-paths.sh
```

### What the Script Does

1. Scans `editor/editor/index.html`
2. Replaces all `"/_next/` with `"../_next/`
3. Replaces all `'/_next/` with `'../_next/`
4. Reports the number of paths fixed

### Sample Output

```
=== Fixing Next.js Build Paths ===

Processing: /var/www/html/ojs-3_3/plugins/generic/xmlEditor/editor/editor/index.html
Found 13 hardcoded /_next/ paths in href/src attributes
Remaining hardcoded paths: 0
Fixed relative paths: 13

✓ Success! All href/src paths have been fixed.
Note: Some /_next/ strings may remain in inline JSON data (this is normal).
```

## Automated Workflow

To automate this process after each build, you can:

### Option 1: Add to Your Build Script

```bash
# In your Next.js project build script
npm run build
npm run export  # or whatever creates your static export
cp -r out/* /var/www/html/ojs-3_3/plugins/generic/xmlEditor/editor/
/var/www/html/ojs-3_3/plugins/generic/xmlEditor/fix-nextjs-paths.sh
```

### Option 2: Create a Deployment Script

Create a `deploy.sh` in your Next.js project:

```bash
#!/bin/bash
set -e

echo "Building Next.js app..."
npm run build

echo "Copying to OJS plugin..."
cp -r out/* /var/www/html/ojs-3_3/plugins/generic/xmlEditor/editor/

echo "Fixing Next.js paths..."
/var/www/html/ojs-3_3/plugins/generic/xmlEditor/fix-nextjs-paths.sh

echo "Deployment complete!"
```

Make it executable:
```bash
chmod +x deploy.sh
```

Then run it:
```bash
./deploy.sh
```

## Troubleshooting

### Assets Not Loading (404 errors)

**Symptom:** JavaScript, CSS, or font files return 404 errors

**Solution:** Run the path fixing script:
```bash
./fix-nextjs-paths.sh
```

### Editor Not Displaying

**Symptom:** Blank page or "Loading editor..." message persists

**Possible causes:**
1. Paths not fixed - run `fix-nextjs-paths.sh`
2. Next.js build incomplete - rebuild your Next.js app
3. File permissions - ensure web server can read the files

Check browser console for specific errors.

### Script Errors

**Error:** "Editor directory not found"
- Ensure the Next.js build is copied to `editor/` directory

**Error:** "index.html not found"
- Ensure the Next.js build includes `editor/index.html` (or adjust your build output structure)

## Notes

- The iframe approach isolates the Next.js app completely
- No need to modify OJS core or template system beyond the simple iframe
- All Next.js routing and features work as normal within the iframe
- Some `/_next/` strings may remain in inline JSON data - this is normal and doesn't affect functionality
