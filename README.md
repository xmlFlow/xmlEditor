# XML Editor Plugin for OJS

A lightweight OJS plugin extracted from the Texture plugin, focused on XML file editing with image/media management capabilities.

## Features

This plugin provides:

1. **XML File Editing**
   - Open and edit XML files directly in the OJS workflow
   - Save changes back to the submission file
   - Preserves XML structure and metadata

2. **Media/Image Management APIs**
   - Upload images and media files via API
   - Retrieve embedded images from XML documents
   - Delete media files
   - Automatic association of media with XML documents as dependent files

3. **Workflow Integration**
   - Adds "Edit XML" action to file grids
   - Works across multiple workflow stages:
     - Editor submission details
     - Review files
     - Copyedit files
     - Production ready files

## File Structure

```
xmlEditor/
├── classes/
│   └── DAR.inc.php              # JSON document structure builder
├── controllers/
│   └── XmlEditorHandler.inc.php # Main request handler (editor, JSON API, media API)
├── locale/
│   └── en_US/
│       └── locale.po            # English translations
├── templates/
│   └── editor.tpl               # Editor interface template
├── index.php                    # Plugin entry point
├── version.xml                  # Plugin metadata
└── XmlEditorPlugin.inc.php      # Main plugin class

```

## API Endpoints

### 1. Editor Interface
- **Route:** `xmlEditor/editor`
- **Method:** GET
- **Parameters:**
  - `submissionId`: Submission ID
  - `submissionFileId`: File ID to edit
  - `stageId`: Workflow stage ID
- **Description:** Opens the XML editor interface

### 2. Document API
- **Route:** `xmlEditor/json`
- **Methods:**
  - **GET**: Load XML document as JSON for editing
  - **PUT**: Save edited XML document or upload media
  - **DELETE**: Remove media file
- **Parameters:**
  - `submissionId`: Submission ID
  - `submissionFileId`: File ID
  - `stageId`: Workflow stage ID

### 3. Media API
- **Route:** `xmlEditor/media`
- **Method:** GET
- **Parameters:**
  - `assocId`: Associated XML file ID
  - `fileId`: Media file ID
- **Description:** Serves media files (images, etc.) associated with XML documents

## Key Methods

### XmlEditorHandler.inc.php

- **`editor()`** (lines 73-113): Displays the XML editor interface
- **`json()`** (lines 121-266): Handles document loading and saving
  - GET: Loads document as JSON
  - PUT: Saves document or uploads media
  - DELETE: Removes media files
- **`updateManuscriptFile()`** (lines 274-318): Updates XML file content
- **`media()`** (lines 326-369): Serves media files with proper headers
- **`_getGenreId()`** (lines 377-395): Determines genre for uploaded media

### DAR.inc.php

- **`construct()`**: Creates JSON structure for editor
- **`createManuscript()`**: Prepares XML for editing
- **`createManifest()`**: Generates manifest with asset references
- **`createMediaInfo()`**: Builds media URL mappings
- **`getDependentFilePaths()`**: Retrieves associated media files

## What Was Excluded

The following Texture-specific features were **NOT** included:

- JATS metadata manipulation (JATS.inc.php)
- DAR/ZIP archive export functionality
- DAR/ZIP archive extraction functionality
- Galley creation features
- Service file handlers (ORKG, OAI-PMH integration)
- Publication workflow forms

## Installation

1. Place the `xmlEditor` directory in `/plugins/generic/`
2. Navigate to Settings > Website > Plugins in OJS
3. Find "XML Editor Plugin" and click Enable
4. The plugin will add "Edit XML" links to XML files in the workflow

## Usage

1. Upload an XML file to any workflow stage
2. Click the "Edit XML" link that appears next to the XML file
3. Edit the document in the editor interface
4. Upload images via the editor interface
5. Save changes back to OJS

## Requirements

- OJS 3.3.x
- PHP 7.3 or higher
- XML and DOM extensions enabled

## License

Copyright (c) 2003-2022 Simon Fraser University
Copyright (c) 2003-2022 John Willinsky
Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.

## Credits

Extracted and simplified from the OJS Texture Plugin.
