# DOCX Converter Plugin Exploration - Complete Index

## Overview

This directory contains a comprehensive exploration of the DOCX Converter plugin located at:
`/var/www/html/ojs-3_3/plugins/generic/docxConverter/`

The exploration was conducted to understand image extraction patterns, file handling, and conversion methods that can be adopted in the XML Editor plugin.

---

## Generated Documents

### 1. DOCX_CONVERTER_ANALYSIS.md
**Purpose**: Deep technical analysis and architecture documentation

**Contents**:
- Executive summary of plugin capabilities
- Image extraction logic breakdown
- Conversion method pipeline
- File storage and dependent files architecture
- Reusable functions and patterns
- Architectural insights and design patterns
- Security considerations
- Implementation checklist

**Size**: 27 KB, 893 lines
**Best for**: Understanding overall architecture and design patterns

**Key Sections**:
1. Image Extraction Logic (ZIP archive, DOMXPath, relationship resolution)
2. Conversion Methods (two-phase processing, handler orchestration)
3. File Storage Strategy (PrivateFileManager, SubmissionFile DAO, dependent files)
4. Reusable Patterns (Archive extraction, namespace-aware XPath, binary file handling)
5. Architectural Insights (separation of concerns, error handling)

---

### 2. DOCX_CONVERTER_CODE_EXAMPLES.md
**Purpose**: Copy-paste ready code implementations

**Contents**:
- ZIP archive extraction template
- File extraction and storage handler
- OJS submission file creation helper
- Complete image extraction handler
- Plugin configuration pattern
- Error handling template
- Usage workflow summary

**Size**: 28 KB, 904 lines
**Best for**: Implementation and code reference

**Key Templates**:
1. DocumentArchive class (extends ZipArchive)
2. FileExtractor class (binary handling, MIME detection)
3. SubmissionFileHelper class (submission file creation)
4. ImageExtractorHandler class (complete workflow)
5. Plugin registration pattern
6. Error handling with try-catch-finally

---

## Quick Navigation

### If you want to understand...

**Image Extraction**
- See: DOCX_CONVERTER_ANALYSIS.md, Section 1.2
- See: DOCX_CONVERTER_CODE_EXAMPLES.md, Section 1

**File Storage & Dependents**
- See: DOCX_CONVERTER_ANALYSIS.md, Section 3
- See: DOCX_CONVERTER_CODE_EXAMPLES.md, Section 3

**Complete Workflow**
- See: DOCX_CONVERTER_ANALYSIS.md, Section 2.1
- See: DOCX_CONVERTER_CODE_EXAMPLES.md, Sections 4-6

**Security Patterns**
- See: DOCX_CONVERTER_ANALYSIS.md, Section 7

**Implementation Plan**
- See: DOCX_CONVERTER_ANALYSIS.md, Section 8 (Checklist)
- See: DOCX_CONVERTER_CODE_EXAMPLES.md, Usage Summary

---

## Key Patterns to Adopt

### Pattern 1: Archive Extraction
**From**: DOCXArchive.php (lines 105-171)
**Relevance**: Extract media/images from ZIP-based formats
**Location in docs**: 
- Analysis: 1.2 (DOCXArchive.php)
- Code: Section 1 (DocumentArchive class)

### Pattern 2: Binary File Handling
**From**: DOCXConverterHandler.inc.php (lines 108-148, _attachSupplementaryFile)
**Relevance**: Safe extraction, MIME detection, storage workflow
**Location in docs**:
- Analysis: 4.3 (Binary File Handling Pattern)
- Code: Section 2 (FileExtractor class)

### Pattern 3: Dependent Files
**From**: DOCXConverterHandler.inc.php (lines 136-147, setAllData call)
**Relevance**: Create files dependent on parent, proper OJS integration
**Location in docs**:
- Analysis: 3.2 (Dependent File Architecture), 4.5 (Dependent File Pattern)
- Code: Section 3 (SubmissionFileHelper::createDependentFile)

### Pattern 4: Genre Resolution
**From**: DOCXConverterHandler.inc.php (lines 113-121)
**Relevance**: Find appropriate file type genre for context
**Location in docs**:
- Analysis: 3.2 (Genre Resolution Logic), 4.6 (Genre Resolution Pattern)
- Code: Section 3 (SubmissionFileHelper::resolveGenreId)

### Pattern 5: Handler Registration
**From**: DocxToJatsPlugin.inc.php (lines 61-72)
**Relevance**: Register handler, hook system integration
**Location in docs**:
- Analysis: Not detailed but referenced
- Code: Section 5 (Configuration Pattern)

---

## File References

### docxConverter Plugin Structure

```
docxConverter/
├── DOCXConverterHandler.inc.php       ← Main handler (orchestration)
├── DocxToJatsPlugin.inc.php           ← Plugin registration
├── classes/
│   └── DOCXConverterDocument.inc.php  ← Metadata injection
└── docxToJats/
    ├── docxtojats.php                 ← CLI entry point
    └── src/docx2jats/
        ├── DOCXArchive.php            ← Archive extraction
        ├── jats/
        │   ├── Document.php           ← JATS generation
        │   ├── Figure.php             ← Image conversion
        │   └── ...
        └── objectModel/
            ├── Document.php           ← Content extraction
            └── body/
                ├── Image.php          ← Image metadata
                └── ...
```

### Key File Paths
- Main handler: `/var/www/html/ojs-3_3/plugins/generic/docxConverter/DOCXConverterHandler.inc.php`
- Archive class: `/var/www/html/ojs-3_3/plugins/generic/docxConverter/docxToJats/src/docx2jats/DOCXArchive.php`
- Image class: `/var/www/html/ojs-3_3/plugins/generic/docxConverter/docxToJats/src/docx2jats/objectModel/body/Image.php`

---

## Implementation Workflow

### Phase 1: Archive Extraction
1. Read file from PrivateFileManager storage
2. Open ZIP archive using ZipArchive
3. Enumerate media directory files
4. Extract binary content into memory

**Reference**: Code Examples Section 1 (DocumentArchive class)

### Phase 2: File Processing
1. Write binary data to temp file
2. Detect MIME type
3. Validate against whitelist
4. Extract extension preserving original
5. Generate unique filename
6. Store in submission directory

**Reference**: Code Examples Section 2 (FileExtractor class)

### Phase 3: OJS Registration
1. Create SubmissionFile object
2. Mark as dependent file (SUBMISSION_FILE_DEPENDENT)
3. Link to parent file (assocType, assocId)
4. Resolve appropriate genre (e.g., IMAGE)
5. Register via Services::get('submissionFile')->add()

**Reference**: Code Examples Section 3 (SubmissionFileHelper class)

---

## Critical Implementation Details

### Dependent Files Must Have:
```
fileStage: SUBMISSION_FILE_DEPENDENT
assocType: ASSOC_TYPE_SUBMISSION_FILE
assocId: [parent file ID]
genreId: [resolved from available genres]
```

### MIME Type Detection Fallback Chain:
1. mime_content_type() function
2. finfo functions
3. File extension lookup

### Filename Generation:
```
uniqid() + '.' + original_extension
Result: 507f1f77.png
```

### Error Handling Pattern:
```
try {
    // extraction logic
} catch (Exception $e) {
    // log, skip, or return error
} finally {
    // always cleanup temp files
}
```

---

## Configuration Constants Used

```php
// File stages
SUBMISSION_FILE_SUBMISSION      // Original submission
SUBMISSION_FILE_COPYEDITING     // Copyediting stage
SUBMISSION_FILE_PRODUCTION      // Production stage
SUBMISSION_FILE_DEPENDENT       // Dependent/supplementary

// Association types
ASSOC_TYPE_SUBMISSION           // File to submission
ASSOC_TYPE_SUBMISSION_FILE      // File to file

// Roles
ROLE_ID_MANAGER
ROLE_ID_SUB_EDITOR
ROLE_ID_ASSISTANT

// Workflow stages
WORKFLOW_STAGE_ID_SUBMISSION
WORKFLOW_STAGE_ID_EDITING
WORKFLOW_STAGE_ID_COPYEDITING
WORKFLOW_STAGE_ID_PRODUCTION
```

---

## Service Methods Reference

```php
// File operations
Services::get('file')->add($sourceFile, $destinationPath)
Services::get('submissionFile')->get($fileId)
Services::get('submissionFile')->add($submissionFile, $request)
Services::get('submissionFile')->getSubmissionDir($contextId, $submissionId)
Services::get('submission')->get($submissionId)

// DAO operations
DAORegistry::getDAO('SubmissionFileDAO')->newDataObject()
DAORegistry::getDAO('GenreDAO')->getByDependenceAndContextId($bool, $contextId)

// File operations
PrivateFileManager::getBasePath()
PrivateFileManager::parseFileExtension($filename)
```

---

## Security Checklist

- [ ] Validate MIME type before storage
- [ ] Use SUBMISSION_FILE_DEPENDENT for dependent files
- [ ] Query genres by context (don't hardcode IDs)
- [ ] Always clean up temp files (use finally blocks)
- [ ] Validate file extensions
- [ ] Use PrivateFileManager for storage (not public_html)
- [ ] Restrict operations to authorized roles
- [ ] Check workflow stage access
- [ ] Limit file sizes if needed
- [ ] Log errors for debugging

---

## Next Steps

1. **Review**: Read DOCX_CONVERTER_ANALYSIS.md for full architecture
2. **Study**: Reference DOCX_CONVERTER_CODE_EXAMPLES.md for implementation patterns
3. **Adapt**: Customize code templates for XML Editor specific needs
4. **Test**: Validate with actual DOCX files in test environment
5. **Document**: Create similar analysis for XML Editor's own image extraction

---

## Additional Resources

- OJS Documentation: https://pkp.sfu.ca/
- DOCX Converter GitHub: https://github.com/Vitaliy-1/docxConverter
- OOXML Specification: http://officeopenxml.com/
- JATS Standard: https://jats.nlm.nih.gov/

---

## Document Statistics

| Document | Type | Size | Lines | Focus |
|----------|------|------|-------|-------|
| ANALYSIS | Technical | 27 KB | 893 | Architecture & Patterns |
| CODE_EXAMPLES | Reference | 28 KB | 904 | Implementation & Templates |
| INDEX | Navigation | This file | - | Overview & Reference |

**Total**: ~55 KB of documentation covering all aspects of docxConverter plugin

---

## Contact & Questions

For questions about:
- **Architecture**: See DOCX_CONVERTER_ANALYSIS.md sections 5-8
- **Implementation**: See DOCX_CONVERTER_CODE_EXAMPLES.md sections 1-6
- **Specific Classes**: Search file paths in section "File References"
- **Patterns**: See "Key Patterns to Adopt" section above

---

*Generated: 2025-10-18*
*Source Plugin: docxConverter v1.1.1.0*
*For: XML Editor Plugin Development*

