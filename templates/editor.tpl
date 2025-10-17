{**
 * plugins/generic/xmlEditor/templates/editor.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * XML Editor page
 *}
<!DOCTYPE html>
<html>
	<head>
		<meta charset="UTF-8">
		<meta name="jobId" content="{$documentUrl|escape}">
		<title>{$title|escape}</title>

		{* Editor dependencies - can be customized to use different XML editors *}
		<link href="{$pluginUrl|escape}/editor.css" rel="stylesheet" type="text/css"/>

		{* JavaScript for editor functionality *}
		<script type="text/javascript" src="{$pluginUrl|escape}/editor.js"></script>
	</head>
	<body>
		<div id="xml-editor-container">
			{* Editor will be loaded here via JavaScript *}
		</div>
	</body>
</html>
