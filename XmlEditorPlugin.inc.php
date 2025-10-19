<?php

/**
 * @file plugins/generic/xmlEditor/XmlEditorPlugin.inc.php
 *
 * Copyright (c) 2003-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class XmlEditorPlugin
 * @ingroup plugins_generic_xmleditor
 *
 * @brief XML Editor plugin - Lightweight XML editor with media support
 *
 */

import('lib.pkp.classes.plugins.GenericPlugin');

if (!defined('DAR_MANIFEST_FILE')) {
	define('DAR_MANIFEST_FILE', 'manifest.xml');
}
if (!defined('DAR_MANUSCRIPT_FILE')) {
	define('DAR_MANUSCRIPT_FILE', 'manuscript.xml');
}

/**
 * Class XmlEditorPlugin
 */
class XmlEditorPlugin extends GenericPlugin {
	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.generic.xmlEditor.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.generic.xmlEditor.description');
	}

	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		if (parent::register($category, $path, $mainContextId)) {
			if ($this->getEnabled()) {
				// Register callbacks to add editor action to file grids
				HookRegistry::register('editorsubmissiondetailsfilesgridhandler::initfeatures', [$this, 'addActionsToFileGrid']);
				HookRegistry::register('editorreviewfilesgridhandler::initfeatures', [$this, 'addActionsToFileGrid']);
				HookRegistry::register('copyeditfilesgridhandler::initfeatures', [$this, 'addActionsToFileGrid']);
				HookRegistry::register('productionreadyfilesgridhandler::initfeatures', [$this, 'addActionsToFileGrid']);
				HookRegistry::register('LoadHandler', array($this, 'callbackLoadHandler'));
				HookRegistry::register('TemplateManager::fetch', array($this, 'templateFetchCallback'));

				$this->_registerTemplateResource();
			}
			return true;
		}
		return false;
	}

	/**
	 * Get XML editor URL
	 * @param $request PKPRequest
	 * @return string
	 */
	function getEditorUrl($request) {
		return $this->getPluginUrl($request) . '/editor';
	}

	/**
	 * Get plugin URL
	 * @param $request PKPRequest
	 * @return string
	 */
	function getPluginUrl($request) {
		return $request->getBaseUrl() . '/' . $this->getPluginPath();
	}

	/**
	 * @param $hookName string The name of the invoked hook
	 * @param $args
	 * @return bool
	 * @see PKPPageRouter::route()
	 */
	public function callbackLoadHandler($hookName, $args) {
		$page = $args[0];
		$op = $args[1];

		switch ("$page/$op") {
			case 'xmlEditor/editor':
			case 'xmlEditor/json':
			case 'xmlEditor/media':
			case 'xmlEditor/convertWordToXml':
				define('HANDLER_CLASS', 'XmlEditorHandler');
				define('XMLEDITOR_PLUGIN_NAME', $this->getName());
				$args[2] = $this->getPluginPath() . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . 'XmlEditorHandler.inc.php';
				break;
		}

		return false;
	}

	/**
	 * Add actions to file grid
	 * @param $hookName string The name of the invoked hook
	 * @param $params array Hook parameters
	 * @return bool
	 */
	public function addActionsToFileGrid($hookName, $params) {
		// This hook is called when grid features are initialized
		// The actual action adding is handled by templateFetchCallback
		return false;
	}

	/**
	 * Adds additional links to submission files grid row
	 * @param $hookName string The name of the invoked hook
	 * @param $params array Hook parameters
	 */
	public function templateFetchCallback($hookName, $params) {
		$request = $this->getRequest();
		$router = $request->getRouter();
		$dispatcher = $router->getDispatcher();

		$templateMgr = $params[0];
		$resourceName = $params[1];

		if ($resourceName == 'controllers/grid/gridRow.tpl') {
			$row = $templateMgr->getTemplateVars('row');
			$data = $row->getData();

			if (is_array($data) && (isset($data['submissionFile']))) {
				$submissionFile = $data['submissionFile'];
				$fileExtension = strtolower($submissionFile->getData('mimetype'));

				// Get stage ID
				$stageId = (int)$request->getUserVar('stageId');

				// Add edit action for XML files
				if (strtolower($fileExtension) == 'text/xml') {
					import('lib.pkp.classes.linkAction.request.OpenWindowAction');
					$this->_editWithXmlEditorAction($row, $dispatcher, $request, $submissionFile, $stageId);
				}

				// Add conversion action for DOCX files
				if ($fileExtension == 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
					import('lib.pkp.classes.linkAction.request.PostAndRedirectAction');
					$this->_convertWordToXmlAction($row, $dispatcher, $request, $submissionFile, $stageId);
				}
			}
		}
	}

	/**
	 * Adds edit with XML Editor action to files grid
	 * @param $row SubmissionFilesGridRow
	 * @param Dispatcher $dispatcher
	 * @param PKPRequest $request
	 * @param $submissionFile SubmissionFile
	 * @param int $stageId
	 */
	private function _editWithXmlEditorAction($row, Dispatcher $dispatcher, PKPRequest $request, $submissionFile, int $stageId): void {
		$row->addAction(new LinkAction(
			'xmleditor_editor',
			new OpenWindowAction(
				$dispatcher->url($request, ROUTE_PAGE, null, 'xmlEditor', 'editor', null,
					array(
						'submissionId' => $submissionFile->getData('submissionId'),
						'submissionFileId' => $submissionFile->getData('id'),
						'stageId' => $stageId
					)
				)
			),
			__('plugins.generic.xmlEditor.links.editWithXmlEditor'),
			null
		));
	}

	/**
	 * Adds convert Word to XML action to files grid
	 * @param $row SubmissionFilesGridRow
	 * @param Dispatcher $dispatcher
	 * @param PKPRequest $request
	 * @param $submissionFile SubmissionFile
	 * @param int $stageId
	 */
	private function _convertWordToXmlAction($row, Dispatcher $dispatcher, PKPRequest $request, $submissionFile, int $stageId): void {
		$submissionId = $submissionFile->getData('submissionId');

		$conversionUrl = $dispatcher->url($request, ROUTE_PAGE, null, 'xmlEditor', 'convertWordToXml', null,
			array(
				'submissionId' => $submissionId,
				'submissionFileId' => $submissionFile->getData('id'),
				'stageId' => $stageId
			)
		);

		$redirectUrl = $dispatcher->url($request, ROUTE_PAGE, null, 'workflow', 'access', $submissionId);

		$row->addAction(new LinkAction(
			'xmleditor_convert',
			new PostAndRedirectAction($conversionUrl, $redirectUrl),
			__('plugins.generic.xmlEditor.links.convertWordToXml'),
			null
		));
	}
}
