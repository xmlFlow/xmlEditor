# DOCX Converter Plugin - Comprehensive Exploration Report

## Executive Summary
The `docxConverter` plugin is a sophisticated OJS 3.1+ plugin that converts DOCX (Microsoft Word) documents to JATS XML format. It extracts images from DOCX archives and handles dependent files systematically. The plugin demonstrates excellent patterns for file handling, archive extraction, and OJS integration that are directly applicable to the XML Editor's image extraction needs.

---

## 1. IMAGE EXTRACTION LOGIC

### 1.1 High-Level Flow

```
DOCX File (ZIP Archive)
    ↓
DOCXArchive class opens ZIP
    ↓
Extracts media/images directory
    ↓
Parses drawing elements in XML
    ↓
Creates Image/Figure objects
    ↓
Saves to OJS submission directory
    ↓
Creates dependent files with proper metadata
```

### 1.2 Core Image Extraction Classes

#### **DOCXArchive.php** (`/var/www/html/ojs-3_3/plugins/generic/docxConverter/docxToJats/src/docx2jats/DOCXArchive.php`)

**Purpose**: Handles DOCX file (ZIP archive) unpacking and media file extraction.

**Key Methods**:
- `extractMediaFiles()`: Scans ZIP for `media/` directory files
- `getMediaFilesContent()`: Returns associative array of [filename => binary_data]
- `getMediaFiles(string $outputDir)`: Writes media files to disk with preserved filenames
- `getFile(string $path)`: Retrieves specific files from archive

**Implementation Details**:
```php
// Lines 105-115: Extract media files from archive
private function extractMediaFiles() {
    $paths = array();
    for ($i = 0; $i < $this->numFiles; $i++) {
        $filePath = $this->getNameIndex($i);
        if (!strpos($filePath, "media/")) continue;
        $paths[] = $filePath;
    }
    return $paths;
}

// Lines 156-171: Get media files as binary content
public function getMediaFilesContent(): array {
    $filesContent = array();
    if (empty($this->mediaFiles)) return $filesContent;
    if ($this->open($this->filePath)) {
        foreach ($this->mediaFiles as $mediaFile) {
            $index = $this->locateName($mediaFile);
            $data = $this->getFromIndex($index);
            $filesContent[$mediaFile] = $data;
        }
        $this->close();
    }
    return $filesContent;
}
```

**Key Pattern**: Stores original filename as array key, binary content as value. This preserves file extensions automatically.

#### **Image.php** (`/var/www/html/ojs-3_3/plugins/generic/docxConverter/docxToJats/src/docx2jats/objectModel/body/Image.php`)

**Purpose**: Parses image metadata from OOXML drawing elements.

**Key Methods**:
- `extractLink()`: Extracts relationship ID from `a:blip` element
- `getLink()`: Returns media file path (e.g., "media/image1.png")
- `getFileName()`: Returns basename of media file
- `setCaption()`: Extracts figure caption from document

**Implementation Details**:
```php
// Lines 33-49: Extract image link from OOXML drawing
private function extractLink(): ?string {
    $link = null;
    $relationshipId = null;
    
    $this->getXpath()->registerNamespace("a", "http://schemas.openxmlformats.org/drawingml/2006/main");
    $linkElement = $this->getFirstElementByXpath(".//a:blip", $this->getDomElement());
    if ($linkElement && $linkElement->hasAttribute("r:embed")) {
        $relationshipId = $linkElement->getAttribute("r:embed");
    }
    
    if ($relationshipId) {
        $link = Document::getRelationshipById($relationshipId);
    }
    
    return $link;
}
```

**Namespace Handling**: Uses DOMXPath with registered namespaces to navigate OOXML structure.

#### **Figure.php** (`/var/www/html/ojs-3_3/plugins/generic/docxConverter/docxToJats/src/docx2jats/jats/Figure.php`)

**Purpose**: Converts image metadata to JATS XML format.

**Key Methods**:
- `setContent()`: Creates JATS `<graphic>` element with metadata

**Implementation Details**:
```php
// Lines 27-62: Convert image object to JATS XML element
function setContent() {
    $dataObject = $this->getDataObject();
    
    if ($dataObject->getId()) {
        $this->setAttribute('id', self::JATS_FIGURE_ID_PREFIX . $dataObject->getId());
    }
    
    if ($dataObject->getLabel()) {
        $this->appendChild($this->ownerDocument->createElement('label', $dataObject->getLabel()));
    }
    
    if ($dataObject->getTitle()) {
        $captionNode = $this->ownerDocument->createElement('caption');
        $this->appendChild($captionNode);
        $captionNode->appendChild($this->ownerDocument->createElement('title', $dataObject->getTitle()));
    }
    
    $figureNode = $this->ownerDocument->createElement('graphic');
    $this->appendChild($figureNode);
    
    $pathInfo = pathinfo($this->figureObject->getLink());
    $figureNode->setAttribute("mimetype", "image");
    
    switch ($pathInfo['extension']) {
        case "jpg":
        case "jpeg":
            $figureNode->setAttribute("mime-subtype", "jpeg");
            break;
        case "png":
            $figureNode->setAttribute("mime-subtype", "png");
            break;
    }
    
    $figureNode->setAttribute("xlink:href", $pathInfo['basename']);
}
```

**Output**: JATS XML with image reference (basename only, not full path).

---

## 2. CONVERSION METHODS

### 2.1 Conversion Pipeline

**Entry Point**: `DOCXConverterHandler::parse()` (lines 45-106)

```
1. Load DOCX file via PrivateFileManager
2. Create DOCXArchive object
3. Create DOCXConverterDocument object
4. Extract content and convert to JATS
5. Save JATS XML to temp file
6. Create new SubmissionFile with JATS XML
7. Extract media files
8. Create dependent SubmissionFiles for each media
9. Clean up temp files
```

### 2.2 Key Conversion Classes

#### **DOCXConverterHandler.inc.php** (Main Handler)

**File Path**: `/var/www/html/ojs-3_3/plugins/generic/docxConverter/DOCXConverterHandler.inc.php`

**Key Responsibilities**:
1. Authorization and access control
2. DOCX to JATS conversion orchestration
3. Dependent file creation for images

**Critical Code Section** (lines 45-106):

```php
public function parse($args, $request) {
    // 1. Get submission file
    $submissionFileId = (int) $request->getUserVar('submissionFileId');
    $submissionFile = Services::get('submissionFile')->get($submissionFileId);
    
    // 2. Load DOCX from private storage
    $fileManager = new PrivateFileManager();
    $filePath = $fileManager->getBasePath() . '/' . $submissionFile->getData('path');
    
    // 3. Parse DOCX archive and convert
    $docxArchive = new DOCXArchive($filePath);
    $jatsXML = new DOCXConverterDocument($docxArchive);
    
    // 4. Set document metadata from OJS
    $submissionId = $submissionFile->getData('submissionId');
    $submission = Services::get('submission')->get($submissionId);
    $jatsXML->setDocumentMeta($request, $submission);
    
    // 5. Save JATS XML to temp file
    $tmpfname = tempnam(sys_get_temp_dir(), 'docxConverter');
    file_put_contents($tmpfname, $jatsXML->saveXML());
    
    // 6. Add JATS XML as new submission file
    $genreId = $submissionFile->getData('genreId');
    $submissionDir = Services::get('submissionFile')->getSubmissionDir(
        $submission->getData('contextId'), 
        $submissionId
    );
    $newFileId = Services::get('file')->add(
        $tmpfname,
        $submissionDir . DIRECTORY_SEPARATOR . uniqid() . '.xml'
    );
    
    // 7. Create submission file metadata
    $submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
    $newSubmissionFile = $submissionFileDao->newDataObject();
    $newName = [];
    foreach ($submissionFile->getData('name') as $localeKey => $name) {
        $newName[$localeKey] = pathinfo($name)['filename'] . '.xml';
    }
    
    $newSubmissionFile->setAllData([
        'fileId' => $newFileId,
        'assocType' => $submissionFile->getData('assocType'),
        'assocId' => $submissionFile->getData('assocId'),
        'fileStage' => $submissionFile->getData('fileStage'),
        'mimetype' => 'application/xml',
        'locale' => $submissionFile->getData('locale'),
        'genreId' => $genreId,
        'name' => $newName,
        'submissionId' => $submissionId,
    ]);
    
    $newSubmissionFile = Services::get('submissionFile')->add($newSubmissionFile, $request);
    
    unlink($tmpfname);
    
    // 8. Extract and attach media files as dependent files
    $mediaData = $docxArchive->getMediaFilesContent();
    if (!empty($mediaData)) {
        foreach ($mediaData as $originalName => $singleData) {
            $this->_attachSupplementaryFile(
                $request, 
                $submission, 
                $submissionFileDao, 
                $newSubmissionFile, 
                $fileManager, 
                $originalName, 
                $singleData
            );
        }
    }
    
    return new JSONMessage(true, array(
        'submissionId' => $submissionId,
        'fileId' => $newSubmissionFile->getData('fileId'),
        'fileStage' => $newSubmissionFile->getData('fileStage'),
    ));
}
```

#### **_attachSupplementaryFile() Method** (lines 108-148)

**Purpose**: Creates OJS dependent file entries for extracted images.

```php
private function _attachSupplementaryFile(
    Request $request, 
    Submission $submission, 
    SubmissionFileDAO $submissionFileDao, 
    SubmissionFile $newSubmissionFile, 
    PrivateFileManager $fileManager, 
    string $originalName, 
    string $singleData
) {
    // 1. Write binary data to temp file
    $tmpfnameSuppl = tempnam(sys_get_temp_dir(), 'docxConverter');
    file_put_contents($tmpfnameSuppl, $singleData);
    
    // 2. Detect MIME type
    $mimeType = mime_content_type($tmpfnameSuppl);
    
    // 3. Find appropriate genre for file type
    $genreDao = DAORegistry::getDAO('GenreDAO');
    $genres = $genreDao->getByDependenceAndContextId(
        true,  // dependent files only
        $request->getContext()->getId()
    );
    $supplGenreId = null;
    while ($genre = $genres->next()) {
        if (($mimeType == "image/png" || $mimeType == "image/jpeg") && 
            $genre->getKey() == "IMAGE") {
            $supplGenreId = $genre->getId();
        }
    }
    
    // 4. Skip if no appropriate genre found
    if (!$supplGenreId) {
        unlink($tmpfnameSuppl);
        return;
    }
    
    // 5. Add file to OJS storage
    $submissionDir = Services::get('submissionFile')->getSubmissionDir(
        $submission->getData('contextId'), 
        $submission->getId()
    );
    $newFileId = Services::get('file')->add(
        $tmpfnameSuppl,
        $submissionDir . '/' . uniqid() . '.' . $fileManager->parseFileExtension($originalName)
    );
    
    // 6. Create dependent submission file
    $newSupplementaryFile = $submissionFileDao->newDataObject();
    $newSupplementaryFile->setAllData([
        'fileId' => $newFileId,
        'assocId' => $newSubmissionFile->getId(),
        'assocType' => ASSOC_TYPE_SUBMISSION_FILE,
        'fileStage' => SUBMISSION_FILE_DEPENDENT,  // KEY: Mark as dependent
        'submissionId' => $submission->getId(),
        'genreId' => $supplGenreId,
        'name' => array_fill_keys(
            array_keys($newSubmissionFile->getData('name')), 
            basename($originalName)
        )
    ]);
    
    Services::get('submissionFile')->add($newSupplementaryFile, $request);
    unlink($tmpfnameSuppl);
}
```

**Critical Pattern**: 
- Binary data → temp file → OJS storage → SubmissionFile object
- Uses `SUBMISSION_FILE_DEPENDENT` to mark as dependent
- Links dependent file to parent via `assocType` + `assocId`

### 2.3 DOCXConverterDocument Extension

**File**: `/var/www/html/ojs-3_3/plugins/generic/docxConverter/classes/DOCXConverterDocument.inc.php`

**Purpose**: Extends JATS Document class with OJS-specific metadata injection.

**Key Method** (lines 30-128):
```php
public function setDocumentMeta(Request $request, Submission $submission) {
    // Clears default front matter
    while($this->front->hasChildNodes()) {
        $this->front->removeChild($this->front->firstChild);
    }
    
    // Builds article-meta from OJS submission data
    $articleMeta = $this->createElement("article-meta");
    $this->front->appendChild($articleMeta);
    
    // Adds title, subtitle, authors, affiliations, dates
    // ... (complex nested XML construction)
}
```

**Pattern**: Uses DOMDocument API to build complex nested XML structures programmatically.

---

## 3. FILE STORAGE AND DEPENDENT FILES HANDLING

### 3.1 Storage Strategy

#### **Multi-Level Storage**:

```
PrivateFileManager (base path: /path/to/files/)
    ├── Submission directory (contextId/submissionId/)
    │   ├── 507f1f77-1234/
    │   │   ├── 507f1f77-bcde.xml (Main JATS file)
    │   │   ├── 507f1f77-efgh.png (Image 1 - dependent)
    │   │   ├── 507f1f77-ijkl.jpg (Image 2 - dependent)
    │   │   └── ...
    │   └── 607f1f77-5678/
    │       └── ...
```

#### **Key Components**:

1. **PrivateFileManager**: Manages file I/O in private submission directory
   - Base path determined by system configuration
   - Each submission has unique directory structure
   
2. **SubmissionFile DAO**: Database representation
   - Links files to submissions
   - Tracks file type, genre, stage
   
3. **File Service**: Abstract file operations
   ```php
   Services::get('file')->add($sourcePath, $destinationPath);
   Services::get('submissionFile')->add($submissionFileObject, $request);
   Services::get('submissionFile')->getSubmissionDir($contextId, $submissionId);
   ```

### 3.2 Dependent File Architecture

#### **Parent-Child Relationship**:

```
Parent File (JATS XML)
    ├── assocType: ASSOC_TYPE_SUBMISSION_FILE
    ├── assocId: [parent file ID]
    ├── fileStage: SUBMISSION_FILE_SUBMISSION (or COPYEDITING, PRODUCTION)
    
Dependent File (Image)
    ├── assocType: ASSOC_TYPE_SUBMISSION_FILE  ← Same type
    ├── assocId: [parent file ID]              ← Points to parent
    ├── fileStage: SUBMISSION_FILE_DEPENDENT    ← KEY marker
    ├── genreId: IMAGE                          ← File type
```

#### **OJS Constants Used**:

```php
// File stages
SUBMISSION_FILE_SUBMISSION    // Original submission stage
SUBMISSION_FILE_COPYEDITING   // Copyediting stage
SUBMISSION_FILE_PRODUCTION    // Production stage
SUBMISSION_FILE_DEPENDENT     // Dependent/supplementary file

// Association types
ASSOC_TYPE_SUBMISSION_FILE    // File associated with another file

// Genre keys
"IMAGE"                       // Genre key for image files
```

#### **Genre Resolution Logic** (lines 113-121):

```php
// Query genres that support dependence
$genres = $genreDao->getByDependenceAndContextId(
    true,  // Filter: dependent files only
    $request->getContext()->getId()
);

// Find genre by file type
$supplGenreId = null;
while ($genre = $genres->next()) {
    if (($mimeType == "image/png" || $mimeType == "image/jpeg") && 
        $genre->getKey() == "IMAGE") {
        $supplGenreId = $genre->getId();
    }
}
```

**Pattern**: Always query available genres by context, don't assume IDs.

### 3.3 File Extension Preservation

**Key Pattern** - Lines 131:
```php
$submissionDir . '/' . uniqid() . '.' . $fileManager->parseFileExtension($originalName)
```

**Strategy**:
1. Extract extension from original filename
2. Generate unique filename with `uniqid()`
3. Append original extension
4. Result: `507f1f77.png` (preserves image type while avoiding name collisions)

---

## 4. REUSABLE FUNCTIONS AND PATTERNS

### 4.1 Archive Extraction Pattern

**Pattern**: DOCX as ZIP archive

```php
use \ZipArchive;

class DOCXArchive extends \ZipArchive {
    public function __construct(string $filepath) {
        if ($this->open($filepath)) {
            // Extract specific XML files
            $contentType = $this->transformToXml(self::CONTENT_TYPES_PATH);
            
            // Extract media
            $this->mediaFiles = $this->extractMediaFiles();
            
            $this->close();
        }
    }
    
    private function transformToXml(string $path): ?\DOMDocument {
        $index = $this->locateName($path);
        if ($index === false) return null;
        $data = $this->getFromIndex($index);
        $xml = new \DOMDocument();
        $xml->loadXML($data, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
        return $xml;
    }
}
```

**Applicability**: Directly applicable to any ZIP-based format (including Office Open XML, e-pub, etc.).

### 4.2 Namespace-Aware XPath Pattern

**Pattern**: Registering and querying namespaced XML

```php
// Register namespace before query
$xpath->registerNamespace("a", "http://schemas.openxmlformats.org/drawingml/2006/main");

// Query with namespace prefix
$linkElement = $xpath->query(".//a:blip");

// Extract relationship reference
if ($linkElement->hasAttribute("r:embed")) {
    $relationshipId = $linkElement->getAttribute("r:embed");
}

// Resolve via relationships document
$link = Document::getRelationshipById($relationshipId);
```

**Applicability**: Essential for OOXML parsing; patterns applicable to any XML with multiple namespaces.

### 4.3 Binary File Handling Pattern

**Pattern**: Temp file → Metadata Detection → Storage

```php
// 1. Write binary data to temp file
$tmpfname = tempnam(sys_get_temp_dir(), 'converter');
file_put_contents($tmpfname, $binaryData);

// 2. Detect file type
$mimeType = mime_content_type($tmpfname);

// 3. Extract extension
$extension = pathinfo($originalName, PATHINFO_EXTENSION);

// 4. Move to permanent storage
$newFileId = Services::get('file')->add(
    $tmpfname,
    $submissionDir . '/' . uniqid() . '.' . $extension
);

// 5. Clean up
unlink($tmpfname);
```

**Applicability**: Secure, OJS-compliant file handling with proper cleanup.

### 4.4 OJS Submission File Creation Pattern

**Pattern**: Create and register new submission file

```php
$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
$newSubmissionFile = $submissionFileDao->newDataObject();

$newSubmissionFile->setAllData([
    'fileId' => $newFileId,              // From Services::get('file')->add()
    'assocType' => $sourceFile->getData('assocType'),
    'assocId' => $sourceFile->getData('assocId'),
    'fileStage' => SUBMISSION_FILE_COPYEDITING,
    'mimetype' => 'application/xml',
    'locale' => $sourceFile->getData('locale'),
    'genreId' => $genreId,
    'name' => array_fill_keys(             // Multi-locale filenames
        array_keys($sourceFile->getData('name')), 
        'new-name.xml'
    ),
    'submissionId' => $submissionId,
]);

// Register in system
$newSubmissionFile = Services::get('submissionFile')->add(
    $newSubmissionFile, 
    $request
);
```

**Key Points**:
- Always use `newDataObject()` for new instances
- Copy `assocType`, `assocId`, `locale` from source
- Use `array_fill_keys()` for multi-locale filenames
- Register via Service to trigger OJS event hooks

### 4.5 Dependent File Pattern

**Pattern**: Create file dependent on another file

```php
// Dependent file links to parent file
$dependentFile->setAllData([
    'fileId' => $newFileId,
    'assocId' => $parentFile->getId(),           // Parent file ID
    'assocType' => ASSOC_TYPE_SUBMISSION_FILE,   // Link type
    'fileStage' => SUBMISSION_FILE_DEPENDENT,    // Mark as dependent
    'submissionId' => $submission->getId(),
    'genreId' => $genreId,                       // Must match context
    'name' => array_fill_keys($locales, $filename),
]);

Services::get('submissionFile')->add($dependentFile, $request);
```

**Cascading Effects**:
- When parent is deleted, dependents are also deleted
- Dependent files inherit visibility from parent
- Useful for resources referenced by parent (images, stylesheets, etc.)

### 4.6 Genre Resolution Pattern

**Pattern**: Find appropriate genre for file type

```php
// Query all dependent genres for context
$genreDao = DAORegistry::getDAO('GenreDAO');
$genres = $genreDao->getByDependenceAndContextId(
    true,  // dependent only
    $contextId
);

// Find by key
$targetGenreId = null;
while ($genre = $genres->next()) {
    if ($genre->getKey() == "IMAGE") {
        $targetGenreId = $genre->getId();
        break;
    }
}

// Use only if found
if ($targetGenreId) {
    $file->setData('genreId', $targetGenreId);
}
```

**Anti-Pattern Avoided**: Don't hardcode genre IDs; they vary by installation.

---

## 5. ARCHITECTURAL INSIGHTS

### 5.1 Separation of Concerns

The plugin demonstrates excellent separation:

1. **Archive Layer** (DOCXArchive)
   - ZIP handling
   - Media extraction
   - XML parsing

2. **Object Model Layer** (Document, Image, Table, Par)
   - OOXML interpretation
   - Semantic extraction
   - Relationship resolution

3. **JATS Layer** (jats/Document, jats/Figure, jats/Table)
   - Format conversion
   - XML generation
   - Output structure

4. **OJS Integration Layer** (DOCXConverterHandler, DOCXConverterDocument)
   - File management
   - Metadata injection
   - Workflow integration

### 5.2 Two-Phase Processing

**Phase 1: Parse & Convert**
- Load DOCX archive
- Extract all media files (in memory)
- Convert content to JATS XML
- Generate output JATS file

**Phase 2: Store & Register**
- Write main output to OJS file storage
- Create SubmissionFile record
- For each media file:
  - Write to temp location
  - Detect type/extension
  - Store in submission directory
  - Create dependent SubmissionFile record

**Advantage**: Atomic operation - all media attached to same parent file.

### 5.3 Error Handling Strategy

```php
// Graceful degradation: skip unsupported types
if (!$supplGenreId) {
    unlink($tmpfnameSuppl);
    return;  // Skip this file, continue with others
}

// Relationship fallback
if (!$path = $this->getRealFileDocumentPath($defaultPath, $contentType)) {
    // Try default path
    $path = $defaultPath;
}

// Exception on critical failures
if (!$ooxmlDocument) {
    trigger_error('Cannot find document inside archive', E_USER_ERROR);
}
```

### 5.4 Resource Management

**Temp File Cleanup**:
```php
$tmpfname = tempnam(sys_get_temp_dir(), 'docxConverter');
file_put_contents($tmpfname, $data);
// ... process ...
unlink($tmpfname);  // Always clean up
```

**ZIP Archive Lifecycle**:
```php
if ($this->open($filepath)) {
    // ... work with archive ...
    $this->close();  // Always close
}
```

---

## 6. SPECIFIC APPLICABLE PATTERNS FOR XML EDITOR

### 6.1 Image Extraction from Office Formats

**Recommendation**: Use DOCXArchive as template for:
- ODT (Open Document Text) - also ZIP-based
- OOXML formats
- Any ZIP-based container

**Code Template**:
```php
// Extend ZipArchive
class DocumentArchive extends \ZipArchive {
    public function extractImages($outputDir) {
        foreach ($this->mediaFiles as $mediaFile) {
            $index = $this->locateName($mediaFile);
            $data = $this->getFromIndex($index);
            file_put_contents($outputDir . basename($mediaFile), $data);
        }
    }
}
```

### 6.2 Image-to-File Workflow

**Recommendation**: Adopt the three-step process:

```php
// 1. Extract images with metadata
$images = $archive->extractImages();  // Returns [name => binary]

// 2. Create parent document reference
$mainFile = createJATSFile($document);

// 3. Attach images as dependents
foreach ($images as $name => $binary) {
    attachAsDependent($mainFile, $name, $binary);
}
```

### 6.3 OJS Integration

**Recommendation**: Key patterns to reuse:

1. **Authorization**: Use `WorkflowStageAccessPolicy` for access control
2. **File Storage**: Always use `PrivateFileManager` + `Services::get('file')`
3. **Metadata**: Store all file metadata via `SubmissionFile` DAO
4. **Cleanup**: Always unlink temp files in finally blocks
5. **Error Response**: Return JSONMessage for AJAX actions

### 6.4 Document Structure Preservation

**Pattern**: Preserve original filename + extension pattern

```php
// Original: report_2024.docx
// Extracted image: word/media/image1.png

// Result in OJS:
// - JATS file: 507f1f77.xml (unique ID)
// - Image file: 507f1f77.png (preserves extension)

// Implementation:
$extension = $fileManager->parseFileExtension($originalName);
$newPath = $submissionDir . '/' . uniqid() . '.' . $extension;
```

---

## 7. SECURITY CONSIDERATIONS

### 7.1 Patterns Observed

1. **MIME Type Verification**
   ```php
   $mimeType = mime_content_type($tmpfnameSuppl);
   if (($mimeType == "image/png" || $mimeType == "image/jpeg") && ...) {
       // Process
   }
   ```

2. **Genre Validation**
   - Only attach files if appropriate genre exists
   - Prevents orphaned files
   
3. **Permission Checking**
   - Handler enforces `WorkflowStageAccessPolicy`
   - Only managers, sub-editors, assistants can convert
   - Only at copyediting/production stages

### 7.2 Recommendations for XML Editor

1. **Validate extracted content**: Check MIME types
2. **Restrict to authorized users**: Use same policies as docxConverter
3. **Clean up on errors**: Use try-finally blocks
4. **Validate file extensions**: Use `$fileManager->parseFileExtension()`
5. **Limit file sizes**: Consider max_upload_size

---

## 8. IMPLEMENTATION CHECKLIST

For adopting patterns in XML Editor:

- [ ] Archive extraction (ZIP-based formats)
  - [ ] ZipArchive extension class
  - [ ] Media file enumeration
  - [ ] Binary content retrieval
  
- [ ] Image metadata parsing
  - [ ] Namespace registration
  - [ ] XPath queries
  - [ ] Relationship resolution
  
- [ ] File handling
  - [ ] Temp file creation with proper naming
  - [ ] MIME type detection
  - [ ] Extension preservation
  - [ ] Cleanup on error/completion
  
- [ ] OJS integration
  - [ ] PrivateFileManager usage
  - [ ] SubmissionFile creation
  - [ ] Dependent file linking
  - [ ] Genre resolution
  
- [ ] UI/UX
  - [ ] Handler registration
  - [ ] Grid row action addition
  - [ ] JSONMessage responses
  - [ ] Error messaging

---

## Key Files Reference

| File | Purpose | Lines |
|------|---------|-------|
| DOCXArchive.php | ZIP extraction, media enumeration | 1-230 |
| Image.php | OOXML image parsing | 1-150 |
| Figure.php | JATS XML generation | 1-63 |
| DOCXConverterHandler.inc.php | OJS integration, file management | 1-150 |
| DOCXConverterDocument.inc.php | Metadata injection | 1-171 |
| DOCXConverterPlugin.inc.php | Plugin registration | 1-152 |

---

## Conclusion

The docxConverter plugin is a well-architected solution for document conversion with excellent patterns for:
1. Archive extraction and media handling
2. Complex XML parsing with namespace support
3. OJS-compliant file storage and registration
4. Dependent file management
5. Graceful error handling and resource cleanup

These patterns are directly transferable to XML Editor image extraction and can serve as reference implementations for similar functionality.

