# XML Editor Plugin - Extraction Details

## Overview

This document details what was extracted from the Texture plugin to create the lightweight xmlEditor plugin.

## Source Files (Texture Plugin)

```
texture/
├── TexturePlugin.inc.php          (296 lines)
├── controllers/
│   └── TextureHandler.inc.php     (705 lines)
├── classes/
│   ├── DAR.inc.php                (258 lines)
│   └── JATS.inc.php               (202 lines) [NOT EXTRACTED]
├── handlers/
│   ├── ServiceFileHandler.inc.php [NOT EXTRACTED]
│   ├── ORKGFileHandler.inc.php    [NOT EXTRACTED]
│   └── ORKGHandlerJATSHeader.inc.php [NOT EXTRACTED]
└── controllers/grid/form/
    ├── TextureArticleGalleyForm.inc.php [NOT EXTRACTED]
    └── CreateServiceFileForm.inc.php    [NOT EXTRACTED]
```

## Extracted Files (xmlEditor Plugin)

```
xmlEditor/
├── XmlEditorPlugin.inc.php        (167 lines) - Simplified from TexturePlugin.inc.php
├── controllers/
│   └── XmlEditorHandler.inc.php   (397 lines) - Core methods only from TextureHandler.inc.php
├── classes/
│   └── DAR.inc.php                (258 lines) - Copied as-is
├── templates/
│   └── editor.tpl                 (20 lines)  - Simplified template
├── locale/en_US/
│   └── locale.po                  (45 lines)  - Essential translations only
├── version.xml                    (21 lines)
└── index.php                      (17 lines)
```

## Extracted Functionalities

### 1. From TexturePlugin.inc.php → XmlEditorPlugin.inc.php

**Kept:**
- Plugin registration and initialization
- Hook registrations for file grid integration
- Template callback to add "Edit" actions
- Handler loading mechanism
- `_editWithXmlEditorAction()` method

**Removed:**
- `addActionsToFileGrid()` method (service file support)
- `_exportAction()` method
- `_extractAction()` method (DAR/ZIP extraction)
- `_createGalleyAction()` method
- Support for DAR/ZIP file types

**Changes:**
- Renamed class from `TexturePlugin` to `XmlEditorPlugin`
- Changed routes from `texture/*` to `xmlEditor/*`
- Simplified to only handle XML file editing
- Removed Texture-specific URL methods

### 2. From TextureHandler.inc.php → XmlEditorHandler.inc.php

**Kept Methods:**
- `__construct()` - Constructor with role assignments
- `initialize()` - Request initialization
- `authorize()` - Authorization policy
- `getPlugin()` - Plugin getter
- `editor()` - Display XML editor interface (lines 73-113)
- `json()` - Document API for GET/PUT/DELETE (lines 121-266)
- `updateManuscriptFile()` - Save XML changes (lines 274-318)
- `media()` - Serve media files (lines 326-369)
- `_getGenreId()` - Get genre for media uploads (lines 377-395)

**Removed Methods:**
- `extract()` - DAR/ZIP extraction (93 lines)
- `_createDependentFile()` - Dependent file creation
- `removeFilesAndNotify()` - Cleanup utility
- `rrmdir()` - Recursive directory removal
- `export()` - DAR export functionality
- `_getFileManager()` - File manager getter
- `zipFunctional()` - ZIP extension check
- `createGalley()` - Galley creation
- `createGalleyForm()` - Galley form display
- `createServiceFileForm()` - Service file form

**Changes:**
- Removed DAR extraction logic (~160 lines)
- Removed galley creation logic (~20 lines)
- Removed export functionality (~40 lines)
- Simplified to core editing features only
- Reduced from 705 lines to 397 lines (44% reduction)

### 3. From classes/DAR.inc.php → classes/DAR.inc.php

**Status:** Copied as-is (no changes)

This class is essential for:
- Creating JSON document structure for the editor
- Building manifest files with asset references
- Managing media file information
- Converting between XML and JSON formats

### 4. Locale Files

**Extracted translations:**
- Display name and description
- Editor link text
- Error messages for document operations
- Media upload/delete success/error messages

**Removed translations:**
- Galley creation messages
- DAR/ZIP extraction messages
- Service file messages
- JATS metadata messages

## API Comparison

### Texture Plugin APIs
1. `texture/editor` - Open editor ✅ KEPT
2. `texture/json` - Document API ✅ KEPT
3. `texture/media` - Media API ✅ KEPT
4. `texture/export` - Export DAR ❌ REMOVED
5. `texture/extract` - Extract DAR/ZIP ❌ REMOVED
6. `texture/createGalley` - Create galley ❌ REMOVED
7. `texture/createGalleyForm` - Galley form ❌ REMOVED
8. `texture/createServiceFileForm` - Service file ❌ REMOVED

### xmlEditor Plugin APIs
1. `xmlEditor/editor` - Open editor ✅
2. `xmlEditor/json` - Document API (GET/PUT/DELETE) ✅
3. `xmlEditor/media` - Media API (GET) ✅

## Code Size Comparison

| Component | Texture | xmlEditor | Reduction |
|-----------|---------|-----------|-----------|
| Main Plugin | 296 lines | 167 lines | 44% |
| Handler | 705 lines | 397 lines | 44% |
| Total PHP | 1001 lines | 564 lines | 44% |

## Feature Matrix

| Feature | Texture | xmlEditor |
|---------|---------|-----------|
| Edit XML files | ✅ | ✅ |
| Upload images | ✅ | ✅ |
| Delete images | ✅ | ✅ |
| Serve images | ✅ | ✅ |
| Save XML changes | ✅ | ✅ |
| Export DAR | ✅ | ❌ |
| Extract DAR/ZIP | ✅ | ❌ |
| Create galley | ✅ | ❌ |
| JATS metadata | ✅ | ❌ |
| Service files | ✅ | ❌ |

## Integration Points

### Both plugins integrate with:
- Submission file grids (via template hooks)
- Workflow stages (editor, review, copyedit, production)
- File authorization system
- Dependent file management
- Genre system (for media classification)

### xmlEditor removed integrations with:
- Archive extraction workflows
- Galley creation workflows
- Publication metadata management
- External services (ORKG, OAI-PMH)

## Summary

The xmlEditor plugin is a **minimal, focused version** of the Texture plugin that:
- Reduces codebase by ~44%
- Focuses solely on XML editing and media management
- Removes all publishing workflow features
- Maintains full API compatibility for core editing features
- Can be extended or customized for different XML editors

This extraction creates a lightweight, reusable foundation for XML editing in OJS without the overhead of full JATS publishing workflows.
