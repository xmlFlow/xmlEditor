# DOCX Converter - Code Examples for XML Editor

## Quick Reference: Reusable Code Patterns

This document provides copy-paste ready code patterns extracted from docxConverter plugin for image extraction in XML Editor.

---

## 1. ZIP Archive Extraction (Template)

### Extend ZipArchive for Your Document Format

```php
<?php namespace xmlEditor\classes;

use \ZipArchive;

/**
 * Abstract archive handler for ZIP-based document formats
 * Can be extended for DOCX, ODT, EPUB, etc.
 */
class DocumentArchive extends ZipArchive {
    
    private $filePath;
    private $mediaFiles = [];
    
    /**
     * @param string $filepath Path to ZIP-based document
     */
    public function __construct(string $filepath) {
        $this->filePath = $filepath;
        
        if (!$this->open($filepath)) {
            throw new \Exception("Cannot open archive: {$filepath}");
        }
        
        // Extract media files listing
        $this->extractMediaFiles();
        
        $this->close();
    }
    
    /**
     * Scan archive for media files
     */
    private function extractMediaFiles(): void {
        for ($i = 0; $i < $this->numFiles; $i++) {
            $filePath = $this->getNameIndex($i);
            
            // Check for media directory patterns
            if (preg_match('/media\//i', $filePath) || 
                preg_match('/Pictures\//i', $filePath)) {
                $this->mediaFiles[] = $filePath;
            }
        }
    }
    
    /**
     * Get all media files as binary content
     * @return array [original_path => binary_data]
     */
    public function getMediaFilesContent(): array {
        $filesContent = [];
        
        if (empty($this->mediaFiles)) {
            return $filesContent;
        }
        
        if (!$this->open($this->filePath)) {
            return $filesContent;
        }
        
        foreach ($this->mediaFiles as $mediaFile) {
            $index = $this->locateName($mediaFile);
            if ($index !== false) {
                $data = $this->getFromIndex($index);
                $filesContent[$mediaFile] = $data;
            }
        }
        
        $this->close();
        return $filesContent;
    }
    
    /**
     * Get list of media files in archive
     * @return array List of file paths
     */
    public function getMediaFiles(): array {
        return $this->mediaFiles;
    }
    
    /**
     * Extract XML file from archive as DOMDocument
     * @param string $path Path within archive
     * @return \DOMDocument|null
     */
    public function getXmlFile(string $path): ?\DOMDocument {
        $index = $this->locateName($path);
        
        if ($index === false) {
            return null;
        }
        
        if (!$this->open($this->filePath)) {
            return null;
        }
        
        $data = $this->getFromIndex($index);
        $this->close();
        
        $xml = new \DOMDocument();
        $xml->loadXML($data, LIBXML_NOENT | LIBXML_XINCLUDE);
        
        return $xml;
    }
}
```

---

## 2. File Extraction and Storage Handler

### Complete Binary File Processing Pattern

```php
<?php namespace xmlEditor\classes;

use \ZipArchive;

/**
 * Handles extraction and secure storage of files from archives
 */
class FileExtractor {
    
    private $fileManager;
    private $submissionDir;
    private $tempPrefix = 'xmlEditor';
    
    public function __construct($fileManager, $submissionDir) {
        $this->fileManager = $fileManager;
        $this->submissionDir = $submissionDir;
    }
    
    /**
     * Extract binary data and store in submission directory
     * 
     * @param string $originalName Original filename with extension
     * @param string $binaryData Binary file data
     * @param array $options Storage options
     * @return array File information [fileId, filename, extension, mimeType]
     * @throws \Exception On storage failure
     */
    public function extractAndStore(
        string $originalName, 
        string $binaryData, 
        array $options = []
    ): array {
        
        // Step 1: Write to temp file
        $tmpFile = $this->createTempFile($binaryData);
        
        try {
            // Step 2: Detect MIME type
            $mimeType = $this->detectMimeType($tmpFile);
            
            // Step 3: Validate file type (optional)
            if (isset($options['allowedTypes'])) {
                $this->validateMimeType($mimeType, $options['allowedTypes']);
            }
            
            // Step 4: Extract extension preserving original
            $extension = $this->parseFileExtension($originalName);
            
            // Step 5: Generate unique filename
            $uniqueFilename = $this->generateUniqueFilename($extension);
            
            // Step 6: Store in submission directory
            $destinationPath = $this->submissionDir . DIRECTORY_SEPARATOR . $uniqueFilename;
            $fileId = $this->storeFile($tmpFile, $destinationPath);
            
            return [
                'fileId' => $fileId,
                'filename' => $uniqueFilename,
                'extension' => $extension,
                'mimeType' => $mimeType,
                'originalName' => basename($originalName),
                'size' => filesize($destinationPath),
            ];
            
        } finally {
            // Always clean up temp file
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }
    
    /**
     * Create temp file with binary data
     */
    private function createTempFile(string $binaryData): string {
        $tmpFile = tempnam(sys_get_temp_dir(), $this->tempPrefix);
        
        if ($tmpFile === false) {
            throw new \Exception("Cannot create temporary file");
        }
        
        $bytes = file_put_contents($tmpFile, $binaryData);
        
        if ($bytes === false) {
            unlink($tmpFile);
            throw new \Exception("Cannot write to temporary file");
        }
        
        return $tmpFile;
    }
    
    /**
     * Detect MIME type using multiple methods
     */
    private function detectMimeType(string $filePath): string {
        // Method 1: mime_content_type (if available)
        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($filePath);
            if ($mime) {
                return $mime;
            }
        }
        
        // Method 2: finfo (more reliable)
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            if ($mime) {
                return $mime;
            }
        }
        
        // Method 3: Fall back to extension
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $typeMap = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
        ];
        
        return $typeMap[$ext] ?? 'application/octet-stream';
    }
    
    /**
     * Validate MIME type against whitelist
     */
    private function validateMimeType(string $mimeType, array $allowed): void {
        if (!in_array($mimeType, $allowed)) {
            throw new \Exception(
                "File type not allowed: {$mimeType}"
            );
        }
    }
    
    /**
     * Parse file extension safely
     */
    private function parseFileExtension(string $filename): string {
        $pathInfo = pathinfo($filename);
        $ext = $pathInfo['extension'] ?? '';
        
        // Remove potentially dangerous extensions
        $ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
        $ext = strtolower($ext);
        
        return $ext ?: 'bin';
    }
    
    /**
     * Generate unique filename with extension
     */
    private function generateUniqueFilename(string $extension): string {
        return uniqid() . '.' . $extension;
    }
    
    /**
     * Store file using OJS file service
     */
    private function storeFile(string $sourcePath, string $destinationPath): string {
        // Use OJS Services if available
        if (class_exists('Services')) {
            return \Services::get('file')->add($sourcePath, $destinationPath);
        }
        
        // Fallback: Manual copy
        if (!copy($sourcePath, $destinationPath)) {
            throw new \Exception("Cannot copy file to storage");
        }
        
        // Return destination as ID
        return $destinationPath;
    }
}
```

---

## 3. OJS Submission File Creation Helper

### Create and Register Submission Files

```php
<?php namespace xmlEditor\classes;

/**
 * Helper for creating OJS submission file records
 */
class SubmissionFileHelper {
    
    /**
     * Create new submission file (non-dependent)
     * 
     * @param int $fileId OJS file ID from Services::get('file')->add()
     * @param int $submissionId Submission ID
     * @param array $params Additional parameters
     * @return \SubmissionFile
     */
    public static function createSubmissionFile(
        int $fileId,
        int $submissionId,
        array $params = []
    ): \SubmissionFile {
        
        $submissionFileDao = \DAORegistry::getDAO('SubmissionFileDAO');
        $submissionFile = $submissionFileDao->newDataObject();
        
        // Get source file as template (if provided)
        $sourceFile = $params['sourceFile'] ?? null;
        
        // Build data array
        $data = [
            'fileId' => $fileId,
            'submissionId' => $submissionId,
            'fileStage' => $params['fileStage'] 
                ?? ($sourceFile ? $sourceFile->getData('fileStage') : SUBMISSION_FILE_SUBMISSION),
            'assocType' => $params['assocType']
                ?? ($sourceFile ? $sourceFile->getData('assocType') : ASSOC_TYPE_SUBMISSION),
            'assocId' => $params['assocId']
                ?? ($sourceFile ? $sourceFile->getData('assocId') : $submissionId),
            'genreId' => $params['genreId']
                ?? ($sourceFile ? $sourceFile->getData('genreId') : null),
            'locale' => $params['locale']
                ?? ($sourceFile ? $sourceFile->getData('locale') : \AppLocale::getLocale()),
            'mimetype' => $params['mimetype'] ?? 'application/octet-stream',
        ];
        
        // Handle multi-locale filenames
        if (isset($params['name'])) {
            if (is_string($params['name'])) {
                // Single name, apply to all locales
                $data['name'] = array_fill_keys(
                    array_keys(\AppLocale::getSupportedLocales()),
                    $params['name']
                );
            } else {
                // Array of locale => name
                $data['name'] = $params['name'];
            }
        } elseif ($sourceFile) {
            // Copy names from source
            $data['name'] = $sourceFile->getData('name');
        } else {
            $data['name'] = ['en' => 'document'];
        }
        
        $submissionFile->setAllData($data);
        return $submissionFile;
    }
    
    /**
     * Create dependent submission file (attached to parent file)
     * 
     * @param int $fileId OJS file ID
     * @param int $submissionId Submission ID
     * @param int $parentFileId Parent submission file ID
     * @param string $genreKey Genre key (e.g., "IMAGE")
     * @param array $params Additional parameters
     * @return \SubmissionFile
     */
    public static function createDependentFile(
        int $fileId,
        int $submissionId,
        int $parentFileId,
        string $genreKey,
        array $params = []
    ): \SubmissionFile {
        
        $submissionFileDao = \DAORegistry::getDAO('SubmissionFileDAO');
        $dependentFile = $submissionFileDao->newDataObject();
        
        // Resolve genre ID from key
        $genreId = self::resolveGenreId($genreKey, $params['contextId'] ?? null);
        
        if (!$genreId) {
            throw new \Exception(
                "Cannot find dependent genre '{$genreKey}' for context"
            );
        }
        
        // Build data array for dependent file
        $data = [
            'fileId' => $fileId,
            'submissionId' => $submissionId,
            'fileStage' => SUBMISSION_FILE_DEPENDENT,  // KEY: Mark as dependent
            'assocType' => ASSOC_TYPE_SUBMISSION_FILE,  // Link to another file
            'assocId' => $parentFileId,                 // Parent file ID
            'genreId' => $genreId,
            'locale' => $params['locale'] ?? \AppLocale::getLocale(),
            'mimetype' => $params['mimetype'] ?? 'application/octet-stream',
        ];
        
        // Filename
        if (isset($params['name'])) {
            if (is_string($params['name'])) {
                $data['name'] = array_fill_keys(
                    array_keys(\AppLocale::getSupportedLocales()),
                    $params['name']
                );
            } else {
                $data['name'] = $params['name'];
            }
        } else {
            $data['name'] = array_fill_keys(
                array_keys(\AppLocale::getSupportedLocales()),
                $params['originalName'] ?? 'resource'
            );
        }
        
        $dependentFile->setAllData($data);
        return $dependentFile;
    }
    
    /**
     * Register submission file in system
     * 
     * @param \SubmissionFile $submissionFile
     * @param \PKPRequest $request
     * @return \SubmissionFile Registered file with ID set
     */
    public static function registerFile(
        \SubmissionFile $submissionFile,
        \PKPRequest $request
    ): \SubmissionFile {
        
        return \Services::get('submissionFile')->add($submissionFile, $request);
    }
    
    /**
     * Resolve genre ID from genre key and context
     * 
     * @param string $genreKey Genre key (e.g., "IMAGE")
     * @param int|null $contextId Context ID (journal/press ID)
     * @return int|null Genre ID or null if not found
     */
    private static function resolveGenreId(
        string $genreKey,
        ?int $contextId = null
    ): ?int {
        
        $genreDao = \DAORegistry::getDAO('GenreDAO');
        
        // If no context provided, try to get from request
        if (!$contextId) {
            $request = \Application::get()->getRequest();
            $context = $request->getContext();
            $contextId = $context ? $context->getId() : null;
        }
        
        if (!$contextId) {
            return null;
        }
        
        // Query genres that support dependence
        $genres = $genreDao->getByDependenceAndContextId(
            true,  // dependent only
            $contextId
        );
        
        // Find matching genre
        while ($genre = $genres->next()) {
            if ($genre->getKey() === $genreKey) {
                return $genre->getId();
            }
        }
        
        return null;
    }
}
```

---

## 4. Complete Image Extraction Handler

### Orchestrates entire extraction process

```php
<?php namespace xmlEditor\controllers;

use \PKPHandler;
use \WorkflowStageAccessPolicy;
use \JSONMessage;

/**
 * Example handler for extracting images from documents
 */
class ImageExtractorHandler extends PKPHandler {
    
    private $fileExtractor;
    private $submissionFileHelper;
    
    function __construct() {
        parent::__construct();
        
        $this->addRoleAssignment(
            [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT],
            ['extractImages']
        );
    }
    
    function authorize($request, &$args, $roleAssignments) {
        // Require workflow stage access
        $this->addPolicy(new WorkflowStageAccessPolicy(
            $request,
            $args,
            $roleAssignments,
            'submissionId',
            (int)$request->getUserVar('stageId')
        ));
        
        return parent::authorize($request, $args, $roleAssignments);
    }
    
    /**
     * Extract images from document file
     */
    function extractImages($args, $request) {
        
        try {
            // 1. Get submission file
            $submissionFileId = (int)$request->getUserVar('submissionFileId');
            $submissionFile = \Services::get('submissionFile')->get($submissionFileId);
            
            if (!$submissionFile) {
                throw new \Exception("Submission file not found");
            }
            
            // 2. Get file path from private storage
            $fileManager = new \PrivateFileManager();
            $filePath = $fileManager->getBasePath() . '/' . $submissionFile->getData('path');
            
            if (!file_exists($filePath)) {
                throw new \Exception("File not found in storage");
            }
            
            // 3. Open document archive
            $archive = new \xmlEditor\classes\DocumentArchive($filePath);
            
            // 4. Extract media
            $mediaData = $archive->getMediaFilesContent();
            
            if (empty($mediaData)) {
                return new JSONMessage(true, [
                    'message' => 'No images found in document',
                    'count' => 0,
                ]);
            }
            
            // 5. Get submission and context
            $submissionId = $submissionFile->getData('submissionId');
            $submission = \Services::get('submission')->get($submissionId);
            $contextId = $submission->getData('contextId');
            $submissionDir = \Services::get('submissionFile')
                ->getSubmissionDir($contextId, $submissionId);
            
            // 6. Extract and store images
            $extractor = new \xmlEditor\classes\FileExtractor(
                $fileManager,
                $submissionDir
            );
            
            $extractedCount = 0;
            $extractedFiles = [];
            
            foreach ($mediaData as $originalName => $binaryData) {
                try {
                    // Extract and store file
                    $fileInfo = $extractor->extractAndStore(
                        $originalName,
                        $binaryData,
                        [
                            'allowedTypes' => [
                                'image/png',
                                'image/jpeg',
                                'image/gif',
                                'image/svg+xml',
                            ],
                        ]
                    );
                    
                    // Create dependent submission file
                    $dependentFile = \xmlEditor\classes\SubmissionFileHelper
                        ::createDependentFile(
                            $fileInfo['fileId'],
                            $submissionId,
                            $submissionFile->getId(),
                            'IMAGE',  // Genre key
                            [
                                'contextId' => $contextId,
                                'name' => $fileInfo['originalName'],
                                'mimetype' => $fileInfo['mimeType'],
                            ]
                        );
                    
                    // Register in system
                    \xmlEditor\classes\SubmissionFileHelper
                        ::registerFile($dependentFile, $request);
                    
                    $extractedFiles[] = [
                        'name' => $fileInfo['originalName'],
                        'size' => $fileInfo['size'],
                        'type' => $fileInfo['mimeType'],
                    ];
                    
                    $extractedCount++;
                    
                } catch (\Exception $e) {
                    // Log error but continue with other images
                    error_log("Image extraction error: " . $e->getMessage());
                    continue;
                }
            }
            
            return new JSONMessage(true, [
                'message' => "{$extractedCount} images extracted successfully",
                'count' => $extractedCount,
                'files' => $extractedFiles,
            ]);
            
        } catch (\Exception $e) {
            return new JSONMessage(
                false,
                ['message' => $e->getMessage()]
            );
        }
    }
}
```

---

## 5. Configuration Pattern

### Setup in plugin file

```php
<?php namespace xmlEditor;

use \PKPPlugin;

class XmlEditorPlugin extends PKPPlugin {
    
    function register($category, $path, $mainContextId = null) {
        if (parent::register($category, $path, $mainContextId)) {
            if ($this->getEnabled()) {
                // Register handler
                \HookRegistry::register(
                    'LoadHandler',
                    [$this, 'callbackLoadHandler']
                );
                
                // Add UI action
                \HookRegistry::register(
                    'TemplateManager::fetch',
                    [$this, 'templateFetchCallback']
                );
            }
            return true;
        }
        return false;
    }
    
    public function callbackLoadHandler($hookName, $args) {
        $page = $args[0];
        $op = $args[1];
        
        if ($page == "xmlEditorHandler" && $op == "extractImages") {
            define('HANDLER_CLASS', 'ImageExtractorHandler');
            define('PLUGIN_NAME', $this->getName());
            $args[2] = $this->getPluginPath() . '/controllers/ImageExtractorHandler.inc.php';
        }
        
        return false;
    }
    
    public function templateFetchCallback($hookName, $params) {
        $templateMgr = $params[0];
        $resourceName = $params[1];
        
        if ($resourceName == 'controllers/grid/gridRow.tpl') {
            $row = $templateMgr->getTemplateVars('row');
            $data = $row->getData();
            
            if (is_array($data) && isset($data['submissionFile'])) {
                $submissionFile = $data['submissionFile'];
                
                // Add extract action for document files
                if ($this->canExtractImages($submissionFile)) {
                    $request = $this->getRequest();
                    $dispatcher = $request->getDispatcher();
                    
                    $path = $dispatcher->url(
                        $request,
                        ROUTE_PAGE,
                        null,
                        'xmlEditorHandler',
                        'extractImages',
                        null,
                        [
                            'submissionId' => $submissionFile->getData('submissionId'),
                            'submissionFileId' => $submissionFile->getId(),
                            'stageId' => (int)$request->getUserVar('stageId'),
                        ]
                    );
                    
                    import('lib.pkp.classes.linkAction.request.AjaxAction');
                    $linkAction = new \LinkAction(
                        'extractImages',
                        new \AjaxAction($path),
                        __('plugins.generic.xmlEditor.button.extractImages')
                    );
                    
                    $row->addAction($linkAction);
                }
            }
        }
        
        return false;
    }
    
    private function canExtractImages($submissionFile) {
        // Check file type
        $mimeType = $submissionFile->getData('mimetype');
        
        if (!in_array($mimeType, [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.oasis.opendocument.text',
        ])) {
            return false;
        }
        
        // Check permissions and workflow stage
        $request = $this->getRequest();
        $roles = $request->getUser()->getRoles($request->getContext()->getId());
        
        $hasRole = false;
        foreach ($roles as $role) {
            if (in_array($role->getId(), 
                [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT])) {
                $hasRole = true;
                break;
            }
        }
        
        return $hasRole;
    }
}
```

---

## 6. Error Handling Template

### Robust error management pattern

```php
<?php namespace xmlEditor;

/**
 * Wrap extraction in try-catch-finally
 */
class ExtractorWithErrorHandling {
    
    public function extractSafely($filePath, $outputDir) {
        
        $tempFile = null;
        $archive = null;
        
        try {
            // Open archive
            $archive = new DocumentArchive($filePath);
            
            // Extract files
            $media = $archive->getMediaFilesContent();
            
            // Process each file
            foreach ($media as $name => $binary) {
                
                // Create temp file
                $tempFile = tempnam(sys_get_temp_dir(), 'xml_extract');
                file_put_contents($tempFile, $binary);
                
                // Validate
                if (!$this->isValidFile($tempFile)) {
                    throw new \Exception("Invalid file: {$name}");
                }
                
                // Store
                $this->storeFile($tempFile, $outputDir, $name);
                
                // Clean temp
                unlink($tempFile);
                $tempFile = null;
            }
            
            return ['success' => true, 'count' => count($media)];
            
        } catch (\Exception $e) {
            
            // Log error
            error_log("Extraction failed: " . $e->getMessage());
            
            // Return error
            return ['success' => false, 'error' => $e->getMessage()];
            
        } finally {
            
            // Always clean up temp file
            if ($tempFile && file_exists($tempFile)) {
                @unlink($tempFile);
            }
            
            // Close archive if open
            if ($archive) {
                @$archive->close();
            }
        }
    }
    
    private function isValidFile($path) {
        // Add validation logic
        return file_exists($path) && filesize($path) > 0;
    }
    
    private function storeFile($src, $dir, $originalName) {
        $dst = $dir . DIRECTORY_SEPARATOR . basename($originalName);
        if (!copy($src, $dst)) {
            throw new \Exception("Cannot store file");
        }
    }
}
```

---

## Usage Summary

### Complete Workflow

```php
// 1. In handler class:
function extractImages($args, $request) {
    
    // Get file
    $submissionFile = Services::get('submissionFile')->get($fileId);
    $fileManager = new PrivateFileManager();
    $filePath = $fileManager->getBasePath() . '/' . $submissionFile->getData('path');
    
    // Extract images
    $archive = new DocumentArchive($filePath);
    $mediaData = $archive->getMediaFilesContent();
    
    // Store each image
    $extractor = new FileExtractor($fileManager, $submissionDir);
    foreach ($mediaData as $name => $binary) {
        $fileInfo = $extractor->extractAndStore($name, $binary);
        
        // Create dependent file
        $depFile = SubmissionFileHelper::createDependentFile(
            $fileInfo['fileId'],
            $submissionId,
            $submissionFile->getId(),
            'IMAGE',
            ['contextId' => $contextId]
        );
        
        // Register
        SubmissionFileHelper::registerFile($depFile, $request);
    }
    
    return new JSONMessage(true, ['count' => count($mediaData)]);
}
```

