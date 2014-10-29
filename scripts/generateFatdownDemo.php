#!/usr/bin/php
<?php

include __DIR__ . '/../src/autoloader.php';

$configurator = s9e\TextFormatter\Configurator\Bundles\Fatdown::getConfigurator();
$configurator->enableJavaScript();

$configurator->javascript
	->setMinifier('ClosureCompilerService')
	->cacheDir = __DIR__ . '/../tests/.cache';

$configurator->javascript->exportMethods = ['disablePlugin', 'enablePlugin', 'preview'];

$configurator->finalize();

$js = $configurator->javascript->getParser();

ob_start();
?><!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8" />
	<title>s9e\TextFormatter &bull; Fatdown/JS Demo</title>
	<base href="http://s9e.github.io/TextFormatter/fatdown.html" />
	<style type="text/css">
		#preview
		{
			font-family: sans;
			padding: 5px;
			background-color: #f8f8f8;
			border: dashed 1px #ddd;
			border-radius: 5px;
		}
		code
		{
			padding: 2px;
			background-color: #fff;
			border-radius: 3px;
			border: solid 1px #ddd;
		}
		blockquote
		{
			background-color: #fff;
			border: solid 1px #ddd;
			border-left-width: 4px;
			padding-left: 1em;
		}
	</style>

</head>
<body>
	<div style="float:left;width:80%;max-width:800px">
		<form>
			<textarea style="width:99%" rows="15">The Fatdown bundle includes the following plugins:

 - **Autoemail** --- email addresses such as example@example.org are automatically turned into links
 - **Autolink** --- URLs such as http://github.com are automatically turned into links
 - **Escaper** --- special characters can be escaped with a backslash
 - **FancyPants** --- some typography is enhanced, e.g. (c) (tm) and "quotes"
 - **HTMLComments** --- you can use HTML comments
 - **HTMLElements** --- several HTML elements are allowed
 - **HTMLEntities** --- HTML entities such as &amp;hearts; are decoded
 - **Litedown** --- a Markdown*-like* syntax
 - **MediaEmbed** --- URLs from media sites are automatically embedded:<br/>
   http://youtu.be/QH2-TGUlwu4

The parser/renderer used on this page page has been generated by [this script](https://github.com/s9e/TextFormatter/blob/master/scripts/generateFatdownDemo.php). It's been minified with Google Closure Compiler to <?php printf('%.1f', strlen($js) / 1024); ?> KB (<?php printf('%.1f', strlen(gzcompress($js, 9)) / 1024); ?> KB compressed)

***

[![Build Status](https://travis-ci.org/s9e/TextFormatter.png?branch=master)](https://travis-ci.org/s9e/TextFormatter)
[![Coverage Status](https://coveralls.io/repos/s9e/TextFormatter/badge.png)](https://coveralls.io/r/s9e/TextFormatter)
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/s9e/TextFormatter/badges/quality-score.png?s=3942dab3c410fb9ce02001e7446d1083fa91172c)](https://scrutinizer-ci.com/g/s9e/TextFormatter/)</textarea>
		</form>
	</div>

	<div style="float:left;">
		<form><?php

			$list = [];

			foreach ($configurator->plugins as $pluginName => $plugin)
			{
				$list[$pluginName] = '<input type="checkbox" id="' . $pluginName . '" checked="checked" onchange="toggle(this)"><label for="' . $pluginName . '">&nbsp;'. $pluginName . '</label>';
			}

			ksort($list);
			echo implode('<br>', $list);

		?></form>
	</div>

	<div style="clear:both"></div>

	<div id="preview"></div>

	<script type="text/javascript"><?php echo $js; ?>

		var text,
			textareaEl = document.getElementsByTagName('textarea')[0],
			previewEl = document.getElementById('preview');

		window.setInterval(function()
		{
			if (textareaEl.value === text)
			{
				return;
			}

			text = textareaEl.value;
			s9e.TextFormatter.preview(text, previewEl);
		}, 20);

		function toggle(el)
		{
			(el.checked) ? s9e.TextFormatter.enablePlugin(el.id)
			             : s9e.TextFormatter.disablePlugin(el.id);

			text = '';
		}
	</script>
	<script type="text/javascript" src="http://cdnjs.cloudflare.com/ajax/libs/punycode/1.0.0/punycode.min.js"></script>
</body>
</html><?php

file_put_contents(__DIR__ . '/../../s9e.github.io/TextFormatter/fatdown.html', ob_get_clean());

echo "Done.\n";