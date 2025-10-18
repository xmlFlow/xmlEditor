<?php

/**
 * @file plugins/generic/xmlEditor/XmlEditorHandler.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class XmlEditorHandler
 * @ingroup plugins_generic_xmleditor
 *
 * @brief Handle requests for XML Editor plugin
 */

import('classes.handler.Handler');
require_once __DIR__ . "/../lib/docxToJats/vendor/autoload.php";
use docx2jats\DOCXArchive;
use docx2jats\jats\Document;

class XmlEditorHandler extends Handler {
	/** @var Submission * */
	public $submission;
	/** @var Publication * */
	public $publication;
	/** @var XmlEditorPlugin The XML Editor plugin */
	protected $_plugin;

	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();

		$this->_plugin = PluginRegistry::getPlugin('generic', XMLEDITOR_PLUGIN_NAME);
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_REVIEWER, ROLE_ID_AUTHOR),
			array('editor', 'json', 'media', 'convertWordToXml')
		);
	}

	/**
	 * @copydoc PKPHandler::initialize()
	 */
	function initialize($request) {
		parent::initialize($request);
		$this->submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$this->publication = $this->submission->getLatestPublication();
		$this->setupTemplate($request);
	}

	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.WorkflowStageAccessPolicy');
		$this->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', (int)$request->getUserVar('stageId')));
		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * Get the plugin.
	 * @return XmlEditorPlugin
	 */
	function getPlugin() {
		return $this->_plugin;
	}

	/**
	 * Display XML editor
	 *
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string
	 */
	public function editor($args, $request) {
		$stageId = (int)$request->getUserVar('stageId');
		$submissionFileId = (int)$request->getUserVar('submissionFileId');
		$submissionId = (int)$request->getUserVar('submissionId');

		if (!$submissionId || !$stageId || !$submissionFileId) {
			fatalError('Invalid request');
		}

		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$editorTemplateFile = method_exists($this->_plugin, 'getTemplateResource')
			? $this->_plugin->getTemplateResource('editor.tpl')
			: ($this->_plugin->getTemplateResourceName() . ':templates/editor.tpl');

		$router = $request->getRouter();
		$documentUrl = $router->url($request, null, 'xmlEditor', 'json', null,
			array(
				'submissionId' => $submissionId,
				'submissionFileId' => $submissionFileId,
				'stageId' => $stageId
			)
		);

		AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON, LOCALE_COMPONENT_PKP_MANAGER);
		$templateMgr = TemplateManager::getManager($request);
		$publication = $submission->getCurrentPublication();
		$title = $publication->getLocalizedData('title') ?? __('plugins.generic.xmlEditor.name');

		$templateMgr->assign(array(
			'documentUrl' => $documentUrl,
			'editorUrl' => $this->_plugin->getEditorUrl($request),
			'pluginUrl' => $this->_plugin->getPluginUrl($request),
			'title' => $title
		));

		return $templateMgr->fetch($editorTemplateFile);
	}

	/**
	 * Fetch/save JSON document
	 *
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage
	 */
	public function json($args, $request) {
		import('plugins.generic.xmlEditor.classes.DAR');
		$dar = new DAR();

		$submissionFileId = (int)$request->getUserVar('submissionFileId');
		$submissionFile = Services::get('submissionFile')->get($submissionFileId);
		$context = $request->getContext();
		$submissionId = (int)$request->getUserVar('submissionId');

		if (!$submissionFile) {
			fatalError('Invalid request');
		}

		if (empty($submissionFile)) {
			echo __('plugins.generic.xmlEditor.archive.noArticle');
			exit;
		}

		$formLocales = PKPLocale::getSupportedFormLocales();

		// DELETE: Remove media file
		if ($_SERVER["REQUEST_METHOD"] === "DELETE") {
			$postData = file_get_contents('php://input');
			$media = (array)json_decode($postData);

			if (!empty($media)) {
				$dependentFilesIterator = Services::get('submissionFile')->getMany([
					'assocTypes' => [ASSOC_TYPE_SUBMISSION_FILE],
					'assocIds' => [$submissionFileId],
					'submissionIds' => [$submissionId],
					'fileStages' => [SUBMISSION_FILE_DEPENDENT],
					'includeDependentFiles' => true,
				]);

				foreach ($dependentFilesIterator as $dependentFile) {
					$fileName = $dependentFile->getLocalizedData('name');
					if ($fileName == $media['fileName']) {
						Services::get('submissionFile')->delete($dependentFile);
					}
				}
			}
		}

		// GET: Load document for editing
		if ($_SERVER["REQUEST_METHOD"] === "GET") {
			$mediaBlob = $dar->construct($dar, $request, $submissionFile);
			header('Content-Type: application/json');
			return json_encode($mediaBlob, JSON_UNESCAPED_SLASHES);
		}

		// PUT: Save document or upload media
		elseif ($_SERVER["REQUEST_METHOD"] === "PUT") {
			$postData = file_get_contents('php://input');

			if (!empty($postData)) {
				$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
				$postDataJson = json_decode($postData);
				$resources = (isset($postDataJson->archive) && isset($postDataJson->archive->resources))
					? (array)$postDataJson->archive->resources
					: [];
				$media = isset($postDataJson->media) ? (array)$postDataJson->media : [];

				// Upload media file
				if (!empty($media) && array_key_exists("data", $media)) {
					import('lib.pkp.classes.file.FileManager');
					$fileManager = new FileManager();
					$extension = $fileManager->parseFileExtension($media["fileName"]);

					$genreId = $this->_getGenreId($request, $extension);
					if (!$genreId) {
						return new JSONMessage(false);
					}

					$mediaBlob = base64_decode(preg_replace('#^data:\w+/\w+;base64,#i', '', $media["data"]));
					$tempMediaFile = tempnam(sys_get_temp_dir(), 'xmleditor');
					file_put_contents($tempMediaFile, $mediaBlob);

					$submissionDir = Services::get('submissionFile')->getSubmissionDir($context->getData('id'), $submission->getData('id'));
					$fileId = Services::get('file')->add($tempMediaFile, $submissionDir . '/' . uniqid() . '.' . $extension);
					unlink($tempMediaFile);

					$newSubmissionFile = DAORegistry::getDao('SubmissionFileDAO')->newDataObject();
					$newSubmissionFile->setData('fileId', $fileId);
					$newSubmissionFile->setData('name', array_fill_keys(array_keys($formLocales), $media["fileName"]));
					$newSubmissionFile->setData('submissionId', $submission->getData('id'));
					$newSubmissionFile->setData('uploaderUserId', $request->getUser()->getId());
					$newSubmissionFile->setData('assocType', ASSOC_TYPE_SUBMISSION_FILE);
					$newSubmissionFile->setData('assocId', $submissionFile->getData('id'));
					$newSubmissionFile->setData('genreId', $this->_getGenreId($request, $extension));
					$newSubmissionFile->setData('fileStage', SUBMISSION_FILE_DEPENDENT);

					Services::get('submissionFile')->add($newSubmissionFile, $request);
				}
				// Save manuscript XML
				elseif (!empty($resources) && isset($resources[DAR_MANUSCRIPT_FILE]) && is_object($resources[DAR_MANUSCRIPT_FILE])) {
					$this->updateManuscriptFile($request, $resources, $submission, $submissionFile);
				} else {
					return new JSONMessage(false);
				}

				return new JSONMessage(true);
			}
		} else {
			return new JSONMessage(false);
		}
	}

	/**
	 * Update manuscript XML file
	 * @param $request
	 * @param $resources array
	 * @param $submission Article
	 * @param $submissionFile SubmissionFile
	 * @return SubmissionFile
	 */
	protected function updateManuscriptFile($request, $resources, $submission, $submissionFile) {
		$modifiedDocument = new DOMDocument('1.0', 'utf-8');
		$modifiedData = $resources[DAR_MANUSCRIPT_FILE]->data;
		$context = $request->getContext();

		// Write metadata back from original file
		$modifiedDocument->loadXML($modifiedData);
		$xpath = new DOMXpath($modifiedDocument);

		$manuscriptXml = Services::get('file')->fs->read($submissionFile->getData('path'));
		$origDocument = new DOMDocument('1.0', 'utf-8');
		$origDocument->loadXML($manuscriptXml);

		// Replace body section
		$body = $origDocument->documentElement->getElementsByTagName('body')->item(0);
		$origDocument->documentElement->removeChild($body);

		$manuscriptBody = $xpath->query("//article/body");
		foreach ($manuscriptBody as $content) {
			$node = $origDocument->importNode($content, true);
			$origDocument->documentElement->appendChild($node);
		}

		// Replace back section
		$back = $origDocument->documentElement->getElementsByTagName('back')->item(0);
		$origDocument->documentElement->removeChild($back);

		$manuscriptBack = $xpath->query("//article/back");
		foreach ($manuscriptBack as $content) {
			$node = $origDocument->importNode($content, true);
			$origDocument->documentElement->appendChild($node);
		}

		$tmpfname = tempnam(sys_get_temp_dir(), 'xmleditor');
		file_put_contents($tmpfname, $origDocument->saveXML());

		import('lib.pkp.classes.file.FileManager');
		$fileManager = new FileManager();
		$extension = $fileManager->parseFileExtension($submissionFile->getData('path'));
		$submissionDir = Services::get('submissionFile')->getSubmissionDir($context->getData('id'), $submission->getData('id'));
		$fileId = Services::get('file')->add($tmpfname, $submissionDir . '/' . uniqid() . '.' . $extension);

		Services::get('submissionFile')->edit($submissionFile, ['fileId' => $fileId, 'uploaderUserId' => $request->getUser()->getId()], $request);

		unlink($tmpfname);

		return $fileId;
	}

	/**
	 * Display images attached to XML document
	 *
	 * @param $args array
	 * @param $request PKPRequest
	 * @return void
	 */
	public function media($args, $request) {
		$submissionFileId = (int)$request->getUserVar('assocId');
		$submissionFile = Services::get('submissionFile')->get($submissionFileId);

		if (!$submissionFile) {
			fatalError('Invalid request');
		}

		// Make sure submission file is an XML document
		if (!in_array($submissionFile->getData('mimetype'), array('text/xml', 'application/xml'))) {
			fatalError('Invalid request');
		}

		import('lib.pkp.classes.submission.SubmissionFile');
		$dependentFiles = Services::get('submissionFile')->getMany([
			'assocTypes' => [ASSOC_TYPE_SUBMISSION_FILE],
			'assocIds' => [$submissionFile->getData('id')],
			'submissionIds' => [$submissionFile->getData('submissionId')],
			'fileStages' => [SUBMISSION_FILE_DEPENDENT],
			'includeDependentFiles' => true,
		]);

		$mediaFile = null;
		foreach ($dependentFiles as $dependentFile) {
			if ($dependentFile->getData('fileId') == $request->getUserVar('fileId')) {
				$mediaFile = $dependentFile;
				break;
			}
		}

		if (!$mediaFile) {
			$request->getDispatcher()->handle404();
		}

		header('Content-Type:' . $mediaFile->getData('mimetype'));
		$mediaFileContent = Services::get('file')->fs->read($mediaFile->getData('path'));
		header('Content-Length: ' . strlen($mediaFileContent));
		return $mediaFileContent;
	}

	/**
	 * Get genre ID for media files
	 * @param $request PKPRequest
	 * @param $extension string
	 * @return mixed
	 */
	private function _getGenreId($request, $extension) {
		$genreId = null;
		$journal = $request->getJournal();
		$genreDao = DAORegistry::getDAO('GenreDAO');
		$genres = $genreDao->getByDependenceAndContextId(true, $journal->getId());

		while ($candidateGenre = $genres->next()) {
			if ($extension) {
				if ($candidateGenre->getKey() == 'IMAGE') {
					$genreId = $candidateGenre->getId();
					break;
				}
			} else {
				if ($candidateGenre->getKey() == 'MULTIMEDIA') {
					$genreId = $candidateGenre->getId();
					break;
				}
			}
		}
		return $genreId;
	}

	/**
	 * Convert Word document to JATS XML
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage
	 */
	public function convertWordToXml($args, $request) {
		$submissionFileId = (int)$request->getUserVar('submissionFileId');
		$submissionId = (int)$request->getUserVar('submissionId');
		$stageId = (int)$request->getUserVar('stageId');

		$submissionFile = Services::get('submissionFile')->get($submissionFileId);

		if (!$submissionFile) {
			return new JSONMessage(false, __('plugins.generic.xmlEditor.conversion.error'));
		}

		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$context = $request->getContext();

		// Verify file is a DOCX file
		$mimeType = $submissionFile->getData('mimetype');
		if ($mimeType !== 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
			return new JSONMessage(false, __('plugins.generic.xmlEditor.conversion.error'));
		}

		try {
			// Get file path
			import('lib.pkp.classes.file.PrivateFileManager');
			$fileManager = new PrivateFileManager();
			$filePath = $fileManager->getBasePath() . '/' . $submissionFile->getData('path');

			// Convert DOCX to JATS using docxToJats
			$docxArchive = new DOCXArchive($filePath);
			$jatsDocument = new Document($docxArchive);
			$jatsXML = $jatsDocument->saveXML();

			// Save converted XML to temporary file
			$tmpfname = tempnam(sys_get_temp_dir(), 'xmleditor');
			file_put_contents($tmpfname, $jatsXML);

			// Create new XML submission file
			$submissionDir = Services::get('submissionFile')->getSubmissionDir($context->getData('id'), $submissionId);
			$newFileId = Services::get('file')->add(
				$tmpfname,
				$submissionDir . DIRECTORY_SEPARATOR . uniqid() . '.xml'
			);

			// Create new file name (replace .docx with .xml)
			$newName = [];
			foreach ($submissionFile->getData('name') as $localeKey => $name) {
				$newName[$localeKey] = pathinfo($name, PATHINFO_FILENAME) . '.xml';
			}

			// Create new submission file object
			$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
			$newSubmissionFile = $submissionFileDao->newDataObject();
			$newSubmissionFile->setAllData([
				'fileId' => $newFileId,
				'assocType' => $submissionFile->getData('assocType'),
				'assocId' => $submissionFile->getData('assocId'),
				'fileStage' => $submissionFile->getData('fileStage'),
				'mimetype' => 'application/xml',
				'locale' => $submissionFile->getData('locale'),
				'genreId' => $submissionFile->getData('genreId'),
				'name' => $newName,
				'submissionId' => $submissionId,
			]);

			$newSubmissionFile = Services::get('submissionFile')->add($newSubmissionFile, $request);
			unlink($tmpfname);

			// Extract and attach images from DOCX
			$mediaData = $docxArchive->getMediaFilesContent();
			if (!empty($mediaData)) {
				foreach ($mediaData as $originalName => $singleData) {
					$this->_attachImageFile($request, $submission, $submissionFileDao, $newSubmissionFile, $fileManager, $originalName, $singleData);
				}
			}

			return new JSONMessage(true, [
				'submissionId' => $submissionId,
				'fileId' => $newSubmissionFile->getData('fileId'),
				'fileStage' => $newSubmissionFile->getData('fileStage'),
			]);

		} catch (Exception $e) {
			error_log('XML Editor conversion error: ' . $e->getMessage());
			return new JSONMessage(false, __('plugins.generic.xmlEditor.conversion.error'));
		}
	}

	/**
	 * Attach image file extracted from DOCX as dependent file
	 * @param $request PKPRequest
	 * @param $submission Submission
	 * @param $submissionFileDao SubmissionFileDAO
	 * @param $newSubmissionFile SubmissionFile
	 * @param $fileManager PrivateFileManager
	 * @param $originalName string
	 * @param $singleData string
	 */
	private function _attachImageFile($request, $submission, $submissionFileDao, $newSubmissionFile, $fileManager, $originalName, $singleData) {
		$tmpfnameSuppl = tempnam(sys_get_temp_dir(), 'xmleditor');
		file_put_contents($tmpfnameSuppl, $singleData);
		$mimeType = mime_content_type($tmpfnameSuppl);

		// Determine genre for image files
		$genreDao = DAORegistry::getDAO('GenreDAO');
		$genres = $genreDao->getByDependenceAndContextId(true, $request->getContext()->getId());
		$supplGenreId = null;
		while ($genre = $genres->next()) {
			if (($mimeType == "image/png" || $mimeType == "image/jpeg") && $genre->getKey() == "IMAGE") {
				$supplGenreId = $genre->getId();
				break;
			}
		}

		if (!$supplGenreId) {
			unlink($tmpfnameSuppl);
			return;
		}

		$submissionDir = Services::get('submissionFile')->getSubmissionDir($submission->getData('contextId'), $submission->getId());
		$newFileId = Services::get('file')->add(
			$tmpfnameSuppl,
			$submissionDir . '/' . uniqid() . '.' . $fileManager->parseFileExtension($originalName)
		);

		// Create dependent file
		$newSupplementaryFile = $submissionFileDao->newDataObject();
		$newSupplementaryFile->setAllData([
			'fileId' => $newFileId,
			'assocId' => $newSubmissionFile->getId(),
			'assocType' => ASSOC_TYPE_SUBMISSION_FILE,
			'fileStage' => SUBMISSION_FILE_DEPENDENT,
			'submissionId' => $submission->getId(),
			'genreId' => $supplGenreId,
			'name' => array_fill_keys(array_keys($newSubmissionFile->getData('name')), basename($originalName))
		]);

		Services::get('submissionFile')->add($newSupplementaryFile, $request);
		unlink($tmpfnameSuppl);
	}
}
