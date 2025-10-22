<?php

/**
 * @file plugins/generic/xmlEditor/XmlEditorSettingsForm.inc.php
 *
 * Copyright (c) 2003-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class XmlEditorSettingsForm
 * @ingroup plugins_generic_xmleditor
 *
 * @brief Form for journal managers to modify XML Editor plugin settings
 */

import('lib.pkp.classes.form.Form');

class XmlEditorSettingsForm extends Form {

	/** @var int */
	protected $_journalId;

	/** @var object */
	protected $_plugin;

	/**
	 * Constructor
	 * @param $plugin object
	 * @param $journalId int
	 */
	public function __construct($plugin, $journalId) {
		$this->_journalId = $journalId;
		$this->_plugin = $plugin;

		parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	 * Initialize form data.
	 */
	public function initData() {
		$journalId = $this->_journalId;
		$plugin = $this->_plugin;

		$this->setData('reorderReferences', $plugin->getSetting($journalId, 'reorderReferences'));
		$this->setData('splitReferences', $plugin->getSetting($journalId, 'splitReferences'));
		$this->setData('processBrackets', $plugin->getSetting($journalId, 'processBrackets'));
		$this->setData('referenceCheck', $plugin->getSetting($journalId, 'referenceCheck'));
		$this->setData('detailed', $plugin->getSetting($journalId, 'detailed'));
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	public function readInputData() {
		$this->readUserVars(array('reorderReferences', 'splitReferences', 'processBrackets', 'referenceCheck', 'detailed'));
	}

	/**
	 * Fetch the form.
	 * @copydoc Form::fetch()
	 */
	public function fetch($request, $template = null, $display = false) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('pluginName', $this->_plugin->getName());
		return parent::fetch($request, $template, $display);
	}

	/**
	 * @copydoc Form::execute()
	 */
	public function execute(...$functionArgs) {
		$plugin = $this->_plugin;
		$journalId = $this->_journalId;

		$plugin->updateSetting($journalId, 'reorderReferences', $this->getData('reorderReferences'), 'bool');
		$plugin->updateSetting($journalId, 'splitReferences', $this->getData('splitReferences'), 'bool');
		$plugin->updateSetting($journalId, 'processBrackets', $this->getData('processBrackets'), 'bool');
		$plugin->updateSetting($journalId, 'referenceCheck', $this->getData('referenceCheck'), 'bool');
		$plugin->updateSetting($journalId, 'detailed', $this->getData('detailed'), 'bool');

		parent::execute(...$functionArgs);
	}

}
