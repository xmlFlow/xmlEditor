{**
 * plugins/generic/xmlEditor/templates/settingsForm.tpl
 *
 * Copyright (c) 2003-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * XML Editor plugin settings
 *
 *}
<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#xmlEditorSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="xmlEditorSettingsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	<div id="xmlEditorSettings">
		<div id="description">{translate key="plugins.generic.xmlEditor.settings.description"}</div>

		<div class="separator">&nbsp;</div>

		<h3>{translate key="plugins.generic.xmlEditor.settings.jatsConverterOptions"}</h3>

		{csrf}
		{include file="common/formErrors.tpl"}

		{fbvFormArea id="jatsConverterSettingsFormArea"}
			{fbvFormSection list=true title="plugins.generic.xmlEditor.settings.conversionOptions"}
				{fbvElement type="checkbox" id="reorderReferences" name="reorderReferences" checked=$reorderReferences label="plugins.generic.xmlEditor.settings.reorderReferences"}
				{fbvElement type="checkbox" id="splitReferences" name="splitReferences" checked=$splitReferences label="plugins.generic.xmlEditor.settings.splitReferences"}
				{fbvElement type="checkbox" id="processBrackets" name="processBrackets" checked=$processBrackets label="plugins.generic.xmlEditor.settings.processBrackets"}
				{fbvElement type="checkbox" id="referenceCheck" name="referenceCheck" checked=$referenceCheck label="plugins.generic.xmlEditor.settings.referenceCheck"}
				{fbvElement type="checkbox" id="detailed" name="detailed" checked=$detailed label="plugins.generic.xmlEditor.settings.detailed"}
			{/fbvFormSection}
		{/fbvFormArea}

		{fbvFormButtons}
	</div>
</form>
