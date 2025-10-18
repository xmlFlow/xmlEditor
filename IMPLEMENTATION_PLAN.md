# Implementation Plan: Word-to-XML Conversion Feature

## Overview
Add Word document conversion functionality to the OJS XML Editor plugin using the docxToJats project. After conversion, the "Open Editor" button should redirect to the XML editor like the existing "Edit XML" button. 

---

## Phase 1: Add docxToJats Dependency
- Add `docxToJats` as a git submodule in `/var/www/html/ojs-3_3/plugins/generic/xmlEditor/lib/docxToJats`
- Command: `git submodule add https://github.com/withanage/docxToJats lib/docxToJats`
- Ensure PHP composer dependencies are documented (docxToJats requires PHP)

---

## Phase 2: Extend Plugin UI

### Add "Convert Word to XML" Button
- Modify `XmlEditorPlugin.inc.php:templateFetchCallback()` (around line 119-145)
  - Currently detects `text/xml` MIME type → Add detection for Word files
  - Word file MIME types to detect:
    - `application/vnd.openxmlformats-officedocument.wordprocessingml.document` (.docx)
    - `application/msword` (.docx)
   - Also detect, libreoffice files  .odt - application/vnd.oasis.opendocument.text
- Add new private method: `_convertWordToXmlAction()` (similar to `_editWithXmlEditorAction()`)
  - Creates a new LinkAction with appropriate URL
  - Points to new conversion handler endpoint

---

## Phase 3: Create Conversion Handler

### Add to `XmlEditorHandler.inc.php`:

1. **New route handler:** `convertWordToXml()` method
   - Accept same parameters: `submissionId`, `submissionFileId`, `stageId`
   - Load the Word file from OJS storage
   - Call docxToJats converter (via shell_exec or Process component)
   - Save converted JATS XML as new submission file
   - Return JSON response with new file ID
   - Function to extract images as in the docXconverter plugin in /var/www/html/ojs-3_3/plugins/generic/docxConverter 

2. **Conversion process:**
   ```
   User clicks "Convert Word to XML"
   → XmlEditorHandler::convertWordToXml()
   → Extract .docx file to temp location
   → Execute: phplib/docxToJats/docx_to_jats.php input.docx output.xml
   → Read generated JATS XML
   → Create new SubmissionFile with XML MIME type
   → Associate with same submission/stage
   → Return success + new file ID
   ```

3. **Redirect to editor:**
   - Option A: Return redirect URL to open XML editor immediately
   - Option B: Show success message and automatically open editor window
   - Use same editor URL pattern: `/xmlEditor/editor?submissionId=X&submissionFileId=NEW_XML_ID&stageId=Z`

---

## Phase 4: Update Plugin Registration

### Modify `XmlEditorPlugin.inc.php:callbackLoadHandler()`:
- Add new route case: `'xmlEditor/convertWordToXml'`
- Route to `XmlEditorHandler::convertWordToXml()`

### Add role assignment in `XmlEditorHandler::__construct()`:
- Add `'convertWordToXml'` to allowed operations array

---

## Phase 5: Localization

### Add to `locale/en_US/locale.po`:
```
plugins.generic.xmlEditor.links.convertWordToXml = "Convert Word to XML"
plugins.generic.xmlEditor.conversion.success = "Word document converted successfully"
plugins.generic.xmlEditor.conversion.error = "Error converting Word document"
plugins.generic.xmlEditor.conversion.processing = "Converting document..."
```

---

## Phase 6: Error Handling
- Check if PHP is available
- Check if docxToJats dependencies are installed
- Validate Word file format before conversion,if it is docx
- Handle conversion errors gracefully with user-friendly messages
- Clean up temporary files after conversion

---

## Technical Considerations


### 1. File Naming:
- New XML file should be named: `[original-name].xml`
- Or use same name with `.xml` extension

### 2. File Association:
- New XML file should have same:
  - Submission ID
  - Stage ID (production, review, etc.)
  - Genre (likely "Article Text" or similar)

### 3. Dependencies Check:
- Consider adding PHP 8.2+ check for depenceny
- Or document in README.md that server needs PPHP 8.2+  dependencies

### 4. Media/Images:
- docxToJats may extract images from .docx
- Need to upload extracted images as dependent files
- Associate with new XML file

### 5. User Experience:
- Show loading indicator during conversion (may take 5-10 seconds)
- Consider AJAX-based conversion vs page redirect
- Auto-open editor after successful conversion

---

## File Changes Summary

| File | Changes |
|------|---------|
| `XmlEditorPlugin.inc.php` | Add Word file detection, new button action method |
| `controllers/XmlEditorHandler.inc.php` | Add `convertWordToXml()` method, route registration |
| `locale/en_US/locale.po` | Add conversion-related strings |
| `README.md` | Document Python dependencies and setup |
| `.gitmodules` | Add docxToJats submodule |

---

## Testing Checklist
- [ ] Upload .docx file to .odt submission
- [ ] Verify "Convert Word to XML" button appears
- [ ] Click button and verify conversion starts
- [ ] Check converted XML file is created correctly
- [ ] Verify editor opens with converted file
- [ ] Test with document containing images
- [ ] Test error handling (invalid file, Python missing, etc.)
- [ ] Verify all workflow stages (production, review, copyedit)
- [ ] Check if  code contains any errors.

---

## Implementation Steps

### Step 1: Add git submodule
```bash
cd /var/www/html/ojs-3_3/plugins/generic/xmlEditor
git submodule add https://github.com/withanage/docxToJats lib/docxToJats
git submodule update --init --recursive
```

### Step 2: Modify XmlEditorPlugin.inc.php
- Add Word MIME type detection in `templateFetchCallback()`
- Create `_convertWordToXmlAction()` method
- Update `callbackLoadHandler()` to register new route

### Step 3: Modify XmlEditorHandler.inc.php
- Add `convertWordToXml` to role assignments in `__construct()`
- Create `convertWordToXml()` handler method
- Implement conversion logic using docxToJats

### Step 4: Update localization
- Add new strings to `locale/en_US/locale.po`

### Step 5: Update documentation
- Add setup instructions to README.md

### Step 6: Test
- Complete testing checklist above

---

## Notes
- Based on exploration of existing plugin architecture
- Follows same patterns as existing "Edit XML" functionality
- Maintains OJS security and authorization policies
- Preserves file association and workflow stage context

