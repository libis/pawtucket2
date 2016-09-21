<?php	
/* ----------------------------------------------------------------------
 * themes/default/printTemplates/summary/header.php : standard PDF report header
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2014-2016 Whirl-i-Gig
 *
 * For more information visit http://www.CollectiveAccess.org
 *
 * This program is free software; you may redistribute it and/or modify it under
 * the terms of the provided license as published by Whirl-i-Gig
 *
 * CollectiveAccess is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTIES whatsoever, including any implied warranty of 
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
 *
 * This source code is free and modifiable under the terms of 
 * GNU General Public License. (http://www.gnu.org/copyleft/gpl.html). See
 * the "license.txt" file for details, or visit the CollectiveAccess web site at
 * http://www.CollectiveAccess.org
 *
 * -=-=-=-=-=- CUT HERE -=-=-=-=-=-
 * Template configuration:
 *
 * @name Header
 * @type fragment
 *
 * ----------------------------------------------------------------------
 */
 
 if($this->request->config->get('summary_header_enabled')) {
	
	switch($this->getVar('PDFRenderer')) {
		case 'wkhtmltopdf':
?><!--BEGIN HEADER--><!DOCTYPE html><html>
	<head>
		<link type="text/css" href="<?php print $this->getVar('base_path');?>/pdf.css" rel="stylesheet" />
	</head>
	<body style='height:50px;overflow:hidden;margin:0;padding:0;'><div id='header_wk'>
<?php
		print caGetReportImageTag($this->request, ['class' => 'headerImg']);
?>	
	</div>
	<br style="clear: both;"/>
</body>
</html><!--END HEADER-->
<?php
			break;
		case 'PhantomJS':
?>
			<script type="text/javascript">
				// PhantomJS headers are returned as Javascript callbacks (argh). It is not possible to render <img> tags. You must inline all image content (argh * 2).
				PhantomJSPrinting['header'] = {
					height: "80px",
					contents: function(pageNum, numPages) { 
						return "<div style=\"position: absolute; width: 100%; height: 80px; padding: 10px;\">CollectiveAccess</div>";		
					}
				};
			</script>
<?php	
			break;
		default:
?><div id='header'>
<?php
	print caGetReportImageTag($this->request, ['class' => 'headerImg']);
	print "<div class='pagingText'>"._t('Page')." </div>";
?>
</div>
<?php
			break;
	}
}