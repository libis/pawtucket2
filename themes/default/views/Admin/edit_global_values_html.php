<?php
/** ---------------------------------------------------------------------
 * themes/default/views/Admin/edit_global_values_html.php : Edit global values
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2016 Whirl-i-Gig
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
 * @package CollectiveAccess
 * @subpackage Core
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 *
 * ----------------------------------------------------------------------
 */
 
	$va_form_elements = $this->getVar('form_elements');
?>
	<div>
		<h1><?php print _t('Global value editor'); ?></h1>
	</div>
	<div>
		<?php print _t('Global
		'); ?>
	</div>
	<div style="clear:both; height:1px;"><!-- empty --></div>
<?php	
	print caFormTag($this->request, 'saveGlobalValues', 'globalValuesForm', null, 'post', 'multipart/form-data', '_top', ['disableUnsavedChangesWarning' => true]);
	
	foreach($va_form_elements as $vs_name => $va_info) {
?>
		<div class="row">
			<div class="col-md-2"><label class="control-label"><?php print $va_info['label']; ?></label></div>
			<div class="col-md-10"><?php print $va_info['element']; ?></div>
		</div>
<?php
	}
?>
	<button type="submit" class="btn btn-default">Save</button>
</form>