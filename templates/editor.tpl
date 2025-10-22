{**
 * plugins/generic/xmlEditor/templates/editor.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * XML Editor page - Next.js App
 *}
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="jobId" content="{$documentUrl|escape}">
		<title>{$title|escape}</title>
		<style>
			body, html {
				margin: 0;
				padding: 0;
				height: 100%;
				overflow: hidden;
			}
			iframe {
				width: 100%;
				height: 100vh;
				border: none;
				display: block;
			}
		</style>
	</head>
	<body>
		<iframe src="{$pluginUrl|escape}/editor/editor/?submissionId={$submissionId|escape}&submissionFileId={$submissionFileId|escape}&stageId={$stageId|escape}"></iframe>
	</body>
</html>
