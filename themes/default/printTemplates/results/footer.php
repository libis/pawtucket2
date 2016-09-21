<?php
/* ----------------------------------------------------------------------
 * themes/default/printTemplates/results/footer.php : standard PDF report footer
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
 * @name Footer
 * @type fragment
 *
 * ----------------------------------------------------------------------
 */
 	
 	
	$vo_result 				= $this->getVar('result');
	$vn_num_items			= (int)$vo_result->numHits();
	
	 $t_item = $this->getVar('t_subject');
	
if($this->request->config->get('summary_header_enabled')) {
		switch($this->getVar('PDFRenderer')) {
			case 'wkhtmltopdf':
?>
<!--BEGIN FOOTER-->
<!DOCTYPE html>
<html>
	<head>
		<link type="text/css" href="<?php print $this->getVar('base_path');?>/pdf.css" rel="stylesheet" />
		
		<script>
			function dynvar() {
				var vars = {};
				var x = document.location.search.substring(1).split('&');
	
				for (var i in x) {
					var z = x[i].split('=',2);
	
					if (!vars[z[0]]) {
						vars[z[0]] = unescape(z[1]);
					}
				}
	
				document.getElementById('pagingText').innerHTML =  'Page ' + vars.page + ' of ' + vars.topage;
			}
		
		</script>
	</head>
	<body style='height:50px;overflow:hidden;margin:0;padding:0;' onload="dynvar();">
		<div id='footer_wk'><div class='pagingText' id='pagingText'> </div></div>
	</body>
</html>
<!--END FOOTER--><?php
				break;
			case 'PhantomJS':
?>
				<script type="text/javascript">
					PhantomJSPrinting['footer'] = {
						height: "80px",
						contents: function(pageNum, numPages) { 
							return "<div id='footer'><div class='pagingText' id='pagingText'>" + pageNum + "/" + numPages + "</div></div>";
						}
					};
				</script><?php
				break;
			default:
?>
				<div id='footer'><div class='pagingText' id='pagingText'> </div></div><?php
				break;
		}
}