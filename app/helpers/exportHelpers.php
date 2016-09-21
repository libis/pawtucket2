<?php
/** ---------------------------------------------------------------------
 * app/helpers/exportHelpers.php : export and print functions
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
 * @subpackage utils
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 * 
 * ----------------------------------------------------------------------
 */

 /**
   *
   */
	require_once(__CA_LIB_DIR__."/core/Print/PDFRenderer.php");
	require_once(__CA_LIB_DIR__."/core/Print/Barcode.php");
	require_once(__CA_LIB_DIR__."/core/Print/phpqrcode/qrlib.php");
   
	require_once(__CA_LIB_DIR__.'/core/Parsers/PHPExcel/PHPExcel.php');
	require_once(__CA_LIB_DIR__.'/core/Parsers/PHPExcel/PHPExcel/IOFactory.php');
	require_once(__CA_LIB_DIR__.'/core/Parsers/PHPPowerPoint/Autoloader.php');
   
	\PhpOffice\PhpPowerpoint\Autoloader::register();
	
   # ----------------------------------------
	/**
	 * 
	 * @param RequestHTTP $po_request
	 * @param BaseModel $pt_subject
	 * @param string $ps_template The name of the template to render
	 * @param string $ps_output_filename
	 * @param array $pa_options
	 * @return bool
	 *
	 * @throws ApplicationException
	 */
	function caExportItemAsPDF($po_request, $pt_subject, $ps_template, $ps_output_filename, $pa_options=null) {
		$o_view = new View($po_request, $po_request->getViewsDirectoryPath().'/');
		
		$pa_access_values = caGetOption('checkAccess', $pa_options, null);
		
		$o_view->setVar('t_subject', $pt_subject);
		
		$vs_template_identifier = null;
		if (substr($ps_template, 0, 5) === '_pdf_') {
			//
			// Template names starting with "_pdf_" are taken to be named summary templates in the printTemplates/summary directory
			//
			$va_template_info = caGetPrintTemplateDetails('summary', substr($ps_template, 5));
		} elseif (substr($ps_template, 0, 9) === '_display_') {
			//
			// Template names starting with "_display_" are taken to be display_ids to be passed to the standard display formatting template
			//
			$t_display = new ca_bundle_displays($vn_display_id = (int)substr($ps_template, 9));
			
			if ($vn_display_id && ($t_display->isLoaded()) && ($t_display->haveAccessToDisplay($po_request->getUserID(), __CA_BUNDLE_DISPLAY_READ_ACCESS__))) {
				$o_view->setVar('t_display', $t_display);
				$o_view->setVar('display_id', $vn_display_id);
			
				$va_display_list = array();
				$va_placements = $t_display->getPlacements(array('settingsOnly' => true));
				foreach($va_placements as $vn_placement_id => $va_display_item) {
					$va_settings = caUnserializeForDatabase($va_display_item['settings']);
				
					// get column header text
					$vs_header = $va_display_item['display'];
					if (isset($va_settings['label']) && is_array($va_settings['label'])) {
						$va_tmp = caExtractValuesByUserLocale(array($va_settings['label']));
						if ($vs_tmp = array_shift($va_tmp)) { $vs_header = $vs_tmp; }
					}
				
					$va_display_list[$vn_placement_id] = array(
						'placement_id' => $vn_placement_id,
						'bundle_name' => $va_display_item['bundle_name'],
						'display' => $vs_header,
						'settings' => $va_settings
					);
				}
				$o_view->setVar('placements', $va_display_list);
			} else {
				throw new ApplicationException(_t("Invalid format %1", $ps_template));
			}
			$va_template_info = caGetPrintTemplateDetails('summary', 'summary');
		} else {
			throw new ApplicationException(_t("Invalid format %1", $ps_template));
		}
		
		//
		// PDF output
		//
			
		caDoTemplateTagSubstitution($o_view, $pt_subject, $va_template_info['path'], ['checkAccess' => $pa_access_values, 'render' => false]);	
		caExportViewAsPDF($o_view, $va_template_info, $ps_output_filename, []);
	
		return true;
	}
	# ----------------------------------------
	/**
	 * 
	 * @param RequestHTTP $po_request
	 * @param SearchResult $po_result
	 * @param string $ps_template
	 * @param string $ps_output_filename
	 * @param array $pa_options
	 * @return bool
	 *
	 * @throws ApplicationException
	 */
	function caExportResult($po_request, $po_result, $ps_template, $ps_output_filename, $pa_options=null) {
		$o_config = Configuration::load();
		$o_view = new View($po_request, $po_request->getViewsDirectoryPath().'/');
		
		
		$o_view->setVar('result', $po_result);
		$o_view->setVar('criteria_summary', caGetOption('criteriaSummary', $pa_options, ''));
		
		$vs_table = $po_result->tableName();
		
		$vs_type = null;
		if (!(bool)$o_config->get('disable_pdf_output') && substr($ps_template, 0, 5) === '_pdf_') {
			$va_template_info = caGetPrintTemplateDetails('results', substr($ps_template, 5));
			$vs_type = 'pdf';
		} elseif (!(bool)$o_config->get('disable_pdf_output') && (substr($ps_template, 0, 9) === '_display_')) {
			$vn_display_id = substr($ps_template, 9);
			$t_display = new ca_bundle_displays($vn_display_id);
			$o_view->setVar('display', $t_display);
			
			if ($vn_display_id && ($t_display->haveAccessToDisplay($po_request->getUserID(), __CA_BUNDLE_DISPLAY_READ_ACCESS__))) {
				$o_view->setVar('display', $t_display);
				
				$va_placements = $t_display->getPlacements(array('settingsOnly' => true));
				foreach($va_placements as $vn_placement_id => $va_display_item) {
					$va_settings = caUnserializeForDatabase($va_display_item['settings']);
				
					// get column header text
					$vs_header = $va_display_item['display'];
					if (isset($va_settings['label']) && is_array($va_settings['label'])) {
						$va_tmp = caExtractValuesByUserLocale(array($va_settings['label']));
						if ($vs_tmp = array_shift($va_tmp)) { $vs_header = $vs_tmp; }
					}
				
					$va_display_list[$vn_placement_id] = array(
						'placement_id' => $vn_placement_id,
						'bundle_name' => $va_display_item['bundle_name'],
						'display' => $vs_header,
						'settings' => $va_settings
					);
				}
				$o_view->setVar('display_list', $va_display_list);
			} else {
				throw new ApplicationException(_t("Invalid format %1", $ps_template));
			}
			$va_template_info = caGetPrintTemplateDetails('results', 'display');
			$vs_type = 'pdf';
		} elseif(!(bool)$o_config->get('disable_export_output')) {
			// Look it up in app.conf export_formats
			$va_export_config = $o_config->getAssoc('export_formats');
			if (is_array($va_export_config) && is_array($va_export_config[$vs_table]) && is_array($va_export_config[$vs_table][$ps_template])) {
				
				switch($va_export_config[$vs_table][$ps_template]['type']) {
					case 'xlsx':
						$vs_type = 'xlsx';
						break;
					case 'pptx':
						$vs_type = 'pptx';
						break;
				}
			} else {
				throw new ApplicationException(_t("Invalid format %1", $ps_template));
			}
		}
		
		if(!$vs_type) { throw new ApplicationException(_t('Invalid export type')); }
		
		switch($vs_type) {
			case 'xlsx':

				$vn_ratio_pixels_to_excel_height = 0.85;
				$vn_ratio_pixels_to_excel_width = 0.135;

				$va_supercol_a_to_z = range('A', 'Z');
				$vs_supercol = '';

				$va_a_to_z = range('A', 'Z');

				$workbook = new PHPExcel();

				// more accurate (but slower) automatic cell size calculation
				PHPExcel_Shared_Font::setAutoSizeMethod(PHPExcel_Shared_Font::AUTOSIZE_METHOD_EXACT);

				$o_sheet = $workbook->getActiveSheet();
				// mise en forme
				$columntitlestyle = array(
						'font'=>array(
								'name' => 'Arial',
								'size' => 12,
								'bold' => true),
						'alignment'=>array(
								'horizontal'=>PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
								'vertical'=>PHPExcel_Style_Alignment::VERTICAL_CENTER,
								'wrap' => true,
								'shrinkToFit'=> true),
						'borders' => array(
								'allborders'=>array(
										'style' => PHPExcel_Style_Border::BORDER_THICK)));
				$cellstyle = array(
						'font'=>array(
								'name' => 'Arial',
								'size' => 11,
								'bold' => false),
						'alignment'=>array(
								'horizontal'=>PHPExcel_Style_Alignment::HORIZONTAL_LEFT,
								'vertical'=>PHPExcel_Style_Alignment::VERTICAL_CENTER,
								'wrap' => true,
								'shrinkToFit'=> true),
						'borders' => array(
								'allborders'=>array(
										'style' => PHPExcel_Style_Border::BORDER_THIN)));

				$o_sheet->getDefaultStyle()->applyFromArray($cellstyle);
				$o_sheet->setTitle("CollectiveAccess");

				$vn_line = 1;

				$vs_column = reset($va_a_to_z);

				// Column headers
				$o_sheet->getRowDimension($vn_line)->setRowHeight(30);
				foreach($va_export_config[$vs_table][$ps_template]['columns'] as $vs_title => $vs_template) {
					if($vs_column) {
						$o_sheet->setCellValue($vs_supercol.$vs_column.$vn_line,$vs_title);
						$o_sheet->getStyle($vs_supercol.$vs_column.$vn_line)->applyFromArray($columntitlestyle);
						if (!($vs_column = next($va_a_to_z))) {
							$vs_supercol = array_shift($va_supercol_a_to_z);
							$vs_column = reset($va_a_to_z);
						}
					}
				}


				$vn_line = 2 ;

				while($po_result->nextHit()) {
					$vs_column = reset($va_a_to_z);
	
					$va_supercol_a_to_z = range('A', 'Z');
					$vs_supercol = '';

					// default to automatic row height. works pretty well in Excel but not so much in LibreOffice/OOo :-(
					$o_sheet->getRowDimension($vn_line)->setRowHeight(-1);

					foreach($va_export_config[$vs_table][$ps_template]['columns'] as $vs_title => $va_settings) {

						if (
							(strpos($va_settings['template'], 'ca_object_representations.media') !== false)
							&& 
							preg_match("!ca_object_representations\.media\.([A-Za-z0-9_\-]+)!", $va_settings['template'], $va_matches)
						) {
							$vs_version = $va_matches[1];
							$va_info = $po_result->getMediaInfo('ca_object_representations.media', $vs_version);
			
							if($va_info['MIMETYPE'] == 'image/jpeg') { // don't try to insert anything non-jpeg into an Excel file
			
								if (is_file($vs_path = $po_result->getMediaPath('ca_object_representations.media', $vs_version))) {
									$image = "image".$vs_supercol.$vs_column.$vn_line;
									$drawing = new PHPExcel_Worksheet_Drawing();
									$drawing->setName($image);
									$drawing->setDescription($image);
									$drawing->setPath($vs_path);
									$drawing->setCoordinates($vs_supercol.$vs_column.$vn_line);
									$drawing->setWorksheet($o_sheet);
									$drawing->setOffsetX(10);
									$drawing->setOffsetY(10);
								}

								$vn_width = floor(intval($va_info['PROPERTIES']['width']) * $vn_ratio_pixels_to_excel_width);
								$vn_height = floor(intval($va_info['PROPERTIES']['height']) * $vn_ratio_pixels_to_excel_height);

								// set the calculated withs for the current row and column,
								// but make sure we don't make either smaller than they already are
								if($vn_width > $o_sheet->getColumnDimension($vs_supercol.$vs_column)->getWidth()) {
									$o_sheet->getColumnDimension($vs_supercol.$vs_column)->setWidth($vn_width);	
								}
								if($vn_height > $o_sheet->getRowDimension($vn_line)->getRowHeight()){
									$o_sheet->getRowDimension($vn_line)->setRowHeight($vn_height);
								}

							}
						} elseif ($vs_display_text = $po_result->getWithTemplate($va_settings['template'])) {
			
							$o_sheet->setCellValue($vs_supercol.$vs_column.$vn_line, html_entity_decode(strip_tags(br2nl($vs_display_text)), ENT_QUOTES | ENT_HTML5));
							// We trust the autosizing up to a certain point, but
							// we want column widths to be finite :-).
							// Since Arial is not fixed-with and font rendering
							// is different from system to system, this can get a
							// little dicey. The values come from experimentation.
							if ($o_sheet->getColumnDimension($vs_supercol.$vs_column)->getWidth() == -1) {  // don't overwrite existing settings
								if(strlen($vs_display_text)>55) {
									$o_sheet->getColumnDimension($vs_supercol.$vs_column)->setWidth(50);
								}
							}
						}

						if (!($vs_column = next($va_a_to_z))) {
							$vs_supercol = array_shift($va_supercol_a_to_z);
							$vs_column = reset($va_a_to_z);
						}
					}

					$vn_line++;
				}

				// set column width to auto for all columns where we haven't set width manually yet
				foreach(range('A','Z') as $vs_chr) {
					if ($o_sheet->getColumnDimension($vs_chr)->getWidth() == -1) {
						$o_sheet->getColumnDimension($vs_chr)->setAutoSize(true);	
					}
				}

				$o_writer = new PHPExcel_Writer_Excel2007($workbook);

				header('Content-type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
				header('Content-Disposition:inline;filename=Export.xlsx ');
				$o_writer->save('php://output');
				exit;
				break;
			case 'pptx':
				$ppt = new PhpOffice\PhpPowerpoint\PhpPowerpoint();

				$vn_slide = 0;
				while($po_result->nextHit()) {
					if ($vn_slide > 0) {
						$slide = $ppt->createSlide();
					} else {
						$slide = $ppt->getActiveSlide();
					}
			
					foreach($va_export_config[$vs_table][$ps_template]['columns'] as $vs_title => $va_settings) {

						if (
							(strpos($va_settings['template'], 'ca_object_representations.media') !== false)
							&& 
							preg_match("!ca_object_representations\.media\.([A-Za-z0-9_\-]+)!", $va_settings['template'], $va_matches)
						) {
							$vs_version = $va_matches[1];
							$va_info = $po_result->getMediaInfo('ca_object_representations.media', $vs_version);
			
							if($va_info['MIMETYPE'] == 'image/jpeg') { // don't try to insert anything non-jpeg into an Excel file
			
								if (is_file($vs_path = $po_result->getMediaPath('ca_object_representations.media', $vs_version))) {
									$shape = $slide->createDrawingShape();
									$shape->setName($va_info['ORIGINAL_FILENAME'])
										  ->setDescription('Image')
										  ->setPath($vs_path)
										  ->setWidth(caConvertMeasurementToPoints(caGetOption('width', $va_settings, '100px'), array('dpi' => 96)))
										  ->setHeight(caConvertMeasurementToPoints(caGetOption('height', $va_settings, '100px'), array('dpi' => 96)))
										  ->setOffsetX(caConvertMeasurementToPoints(caGetOption('x', $va_settings, '100px'), array('dpi' => 96)))
										  ->setOffsetY(caConvertMeasurementToPoints(caGetOption('y', $va_settings, '100px'), array('dpi' => 96)));
									$shape->getShadow()->setVisible(true)
													   ->setDirection(45)
													   ->setDistance(10);
								}
							}
						} elseif ($vs_display_text = html_entity_decode(strip_tags(br2nl($po_result->getWithTemplate($va_settings['template']))))) {
							switch($vs_align = caGetOption('align', $va_settings, 'center')) {
								case 'center':
									$vs_align = \PhpOffice\PhpPowerpoint\Style\Alignment::HORIZONTAL_CENTER;
									break;
								case 'left':
									$vs_align = \PhpOffice\PhpPowerpoint\Style\Alignment::HORIZONTAL_LEFT;
									break;
								case 'right':
								default:
									$vs_align = \PhpOffice\PhpPowerpoint\Style\Alignment::HORIZONTAL_RIGHT;
									break;
							}
			
							$shape = $slide->createRichTextShape()
								  ->setHeight(caConvertMeasurementToPoints(caGetOption('height', $va_settings, '100px'), array('dpi' => 96)))
								  ->setWidth(caConvertMeasurementToPoints(caGetOption('width', $va_settings, '100px'), array('dpi' => 96)))
								  ->setOffsetX(caConvertMeasurementToPoints(caGetOption('x', $va_settings, '100px'), array('dpi' => 96)))
								  ->setOffsetY(caConvertMeasurementToPoints(caGetOption('y', $va_settings, '100px'), array('dpi' => 96)));
							$shape->getActiveParagraph()->getAlignment()->setHorizontal($vs_align);
							$textRun = $shape->createTextRun($vs_display_text);
							$textRun->getFont()->setBold((bool)caGetOption('bold', $va_settings, false))
											   ->setSize(caConvertMeasurementToPoints(caGetOption('size', $va_settings, '36px'), array('dpi' => 96)))
											   ->setColor( new \PhpOffice\PhpPowerpoint\Style\Color( caGetOption('color', $va_settings, 'cccccc') ) );
						}

					}

					$vn_slide++;
				}

				
				header('Content-type: application/vnd.openxmlformats-officedocument.presentationml.presentation');
				header('Content-Disposition:inline;filename=Export.pptx ');
				
				$o_writer = \PhpOffice\PhpPowerpoint\IOFactory::createWriter($ppt, 'PowerPoint2007');
				$o_writer->save('php://output');
				return;
				break;
			case 'pdf':
				//
				// PDF output
				//
				caExportViewAsPDF($o_view, $va_template_info, caGetOption('filename', $va_template_info, 'export_results.pdf'), []);
			
				return;
		}
	}
	# ----------------------------------------
	/**
	 * Export view as PDF using a specified template. It is assumed that all view variables required for 
	 * rendering are already set.
	 * 
	 * @param View $po_view
	 * @param string $ps_template_identifier
	 * @param string $ps_output_filename
	 * @param array $pa_options
	 * @return bool
	 *
	 * @throws ApplicationException
	 */
	function caExportViewAsPDF($po_view, $ps_template_identifier, $ps_output_filename, $pa_options=null) {
		if (is_array($ps_template_identifier)) {
			$pa_template_info = $ps_template_identifier;
		} else {
			$va_template = explode(':', $ps_template_identifier);
			$pa_template_info = caGetPrintTemplateDetails($va_template[0], $va_template[1]);
		}
		if (!is_array($pa_template_info)) { throw new ApplicationException("No template information specified"); }
		$vb_printed_properly = false;
		
		try {
			$o_pdf = new PDFRenderer();
			$po_view->setVar('PDFRenderer', $o_pdf->getCurrentRendererCode());

			$va_page_size =	PDFRenderer::getPageSize(caGetOption('pageSize', $pa_template_info, 'letter'), 'mm', caGetOption('pageOrientation', $pa_template_info, 'portrait'));
			$vn_page_width = $va_page_size['width']; $vn_page_height = $va_page_size['height'];
			$po_view->setVar('pageWidth', "{$vn_page_width}mm");
			$po_view->setVar('pageHeight', "{$vn_page_height}mm");
			$po_view->setVar('marginTop', caGetOption('marginTop', $pa_template_info, '0mm'));
			$po_view->setVar('marginRight', caGetOption('marginRight', $pa_template_info, '0mm'));
			$po_view->setVar('marginBottom', caGetOption('marginBottom', $pa_template_info, '0mm'));
			$po_view->setVar('marginLeft', caGetOption('marginLeft', $pa_template_info, '0mm'));
			$po_view->setVar('base_path', $vs_base_path = pathinfo($pa_template_info['path'], PATHINFO_DIRNAME));

			$po_view->addViewPath($vs_base_path."/local");
			$po_view->addViewPath($vs_base_path);
			$vs_content = $po_view->render($pa_template_info['path']);
			
			$vb_printed_properly = caExportContentAsPDF($vs_content, $pa_template_info, $ps_output_filename, $pa_options=null);
		} catch (Exception $e) {
			$vb_printed_properly = false;
			throw new ApplicationException(_t("Could not generate PDF"));
		}
		
		return $vb_printed_properly;
	}
	# ----------------------------------------
	/**
	 * Export content as PDF.
	 * 
	 * @param string $ps_content
	 * @param array $pa_template_info
	 * @param string $ps_output_filename
	 * @param array $pa_options
	 * @return bool
	 *
	 * @throws ApplicationException
	 */
	function caExportContentAsPDF($ps_content, $pa_template_info, $ps_output_filename, $pa_options=null) {
		if (!is_array($pa_template_info)) { throw new ApplicationException("No template information specified"); }
		$vb_printed_properly = false;
		
		try {
			$o_pdf = new PDFRenderer();

			$va_page_size =	PDFRenderer::getPageSize(caGetOption('pageSize', $pa_template_info, 'letter'), 'mm', caGetOption('pageOrientation', $pa_template_info, 'portrait'));
			$vn_page_width = $va_page_size['width']; $vn_page_height = $va_page_size['height'];

			$o_pdf->setPage(caGetOption('pageSize', $pa_template_info, 'letter'), caGetOption('pageOrientation', $pa_template_info, 'portrait'), caGetOption('marginTop', $pa_template_info, '0mm'), caGetOption('marginRight', $pa_template_info, '0mm'), caGetOption('marginBottom', $pa_template_info, '0mm'), caGetOption('marginLeft', $pa_template_info, '0mm'));
		
			$ps_output_filename = ($ps_output_filename) ? preg_replace('![^A-Za-z0-9_\-]+!', '_', $ps_output_filename) : 'export';

			$o_pdf->render($ps_content, array('stream'=> true, 'filename' => $ps_output_filename));

			$vb_printed_properly = true;
		} catch (Exception $e) {
			$vb_printed_properly = false;
			throw new ApplicationException(_t("Could not generate PDF"));
		}
		
		return $vb_printed_properly;
	}
	global $g_print_measurement_cache;
	$g_print_measurement_cache = array();

	# ---------------------------------------
	/**
	 *
	 *
	 * @return string
	 */
	function caGetPrintTemplateDirectoryPath($ps_type) {
		$va_paths = [];
		switch($ps_type) {
			case 'results':
				if (is_dir(__CA_THEME_DIR__.'/printTemplates/results')) { $va_paths[] = __CA_THEME_DIR__.'/printTemplates/results'; }
				$va_paths[] = __CA_APP_DIR__.'/printTemplates/results';
				break;
			case 'summary':
				if (is_dir(__CA_THEME_DIR__.'/printTemplates/summary')) { $va_paths[] = __CA_THEME_DIR__.'/printTemplates/summary'; }
				$va_paths[] = __CA_APP_DIR__.'/printTemplates/summary';
				break;
			case 'labels':
				if (is_dir(__CA_THEME_DIR__.'/printTemplates/labels')) { $va_paths[] = __CA_THEME_DIR__.'/printTemplates/labels'; } 
				$va_paths[] = __CA_APP_DIR__.'/printTemplates/labels';
				break;
			case 'bundles':
				if(is_dir(__CA_THEME_DIR__.'/printTemplates/bundles')) { $va_paths[] = __CA_THEME_DIR__.'/printTemplates/bundles'; }
				$va_paths[] = __CA_APP_DIR__.'/printTemplates/bundles';
				break;
		}
		return (sizeof($va_paths) > 0) ? $va_paths : null;
	}
	# ---------------------------------------
	/**
	 *
	 * @param string $ps_type
	 * @param array $pa_options Options include:
	 *		table =
	 *		type =
	 * 		elementCode =
	 *
	 * @return array
	 */
	function caGetAvailablePrintTemplates($ps_type, $pa_options=null) {
		$va_template_paths = caGetPrintTemplateDirectoryPath($ps_type);
		
		$vs_tablename = caGetOption('table', $pa_options, null);
		$vs_type = caGetOption('type', $pa_options, 'page');
		$vs_element_code = caGetOption('elementCode', $pa_options, null);
		$vb_for_html_select = caGetOption('forHTMLSelect', $pa_options, false);


		$vs_cache_key = caMakeCacheKeyFromOptions($pa_options, $ps_type);
		
		$va_templates = array();
			
		foreach($va_template_paths as $vs_template_path) {
			foreach(array("{$vs_template_path}", "{$vs_template_path}/local") as $vs_path) {
				if(!file_exists($vs_path)) { continue; }
		
				if (ExternalCache::contains($vs_cache_key, 'PrintTemplates')) {
					$va_list = ExternalCache::fetch($vs_cache_key, 'PrintTemplates');
					if(
						(ExternalCache::fetch("{$vs_cache_key}_mtime", 'PrintTemplates') >= filemtime($vs_template_path)) &&
						(ExternalCache::fetch("{$vs_cache_key}_local_mtime", 'PrintTemplates') >= filemtime("{$vs_template_path}/local"))
					){
						return $va_list;
					}
				}

				if (is_resource($r_dir = opendir($vs_path))) {
					while (($vs_template = readdir($r_dir)) !== false) {
						if (in_array($vs_template, array(".", ".."))) { continue; }
						$vs_template_tag = pathinfo($vs_template, PATHINFO_FILENAME);
						if (is_array($va_template_info = caGetPrintTemplateDetails($ps_type, $vs_template_tag))) {
							if (caGetOption('type', $va_template_info, null) !== $vs_type)  { continue; }

							if ($vs_element_code && (caGetOption('elementCode', $va_template_info, null) !== $vs_element_code)) { continue; }

							if ($vs_tablename && (!in_array($vs_tablename, $va_template_info['tables'])) && (!in_array('*', $va_template_info['tables']))) {
								continue;
							}

							if (!is_dir($vs_path.'/'.$vs_template) && preg_match("/^[A-Za-z_]+[A-Za-z0-9_]*$/", $vs_template_tag)) {
								if ($vb_for_html_select && !isset($va_templates[$va_template_info['name']])) {
									$va_templates[$va_template_info['name']] = '_pdf_'.$vs_template_tag;
								} elseif (!isset($va_templates[$vs_template_tag])) {
									$va_templates[$vs_template_tag] = array(
										'name' => $va_template_info['name'],
										'code' => '_pdf_'.$vs_template_tag,
										'type' => 'pdf'
									);
								}
							}
						}
					}
				}

				asort($va_templates);
			
				ExternalCache::save($vs_cache_key, $va_templates, 'PrintTemplates');
				ExternalCache::save("{$vs_cache_key}_mtime", filemtime($vs_template_path), 'PrintTemplates');
				ExternalCache::save("{$vs_cache_key}_local_mtime", @filemtime("{$vs_template_path}/local"), 'PrintTemplates');
			}
		}
		return $va_templates;
	}
	# ------------------------------------------------------------------
	/**
	 * @param $ps_type
	 * @param $ps_template
	 * @param null $pa_options
	 * @return array|bool|false|mixed
	 */
	function caGetPrintTemplateDetails($ps_type, $ps_template, $pa_options=null) {
		$va_template_paths = caGetPrintTemplateDirectoryPath($ps_type);
		
		$va_info = [];
		foreach($va_template_paths as $vs_template_path) {
			if (file_exists("{$vs_template_path}/local/{$ps_template}.php")) {
				$vs_template_path = "{$vs_template_path}/local/{$ps_template}.php";
			} elseif(file_exists("{$vs_template_path}/{$ps_template}.php")) {
				$vs_template_path = "{$vs_template_path}/{$ps_template}.php";
			} else {
				continue;
			}

			$vs_cache_key = caMakeCacheKeyFromOptions($pa_options, $ps_type.'/'.$vs_template_path);
			if (ExternalCache::contains($vs_cache_key, 'PrintTemplateDetails')) {
				$va_list = ExternalCache::fetch($vs_cache_key, 'PrintTemplateDetails');
				if(ExternalCache::fetch("{$vs_cache_key}_mtime", 'PrintTemplateDetails') >= filemtime($vs_template_path)) {
					return $va_list;
				}
			}

			$vs_template = file_get_contents($vs_template_path);

			$va_info = [];
			foreach(array(
				"@name", "@type", "@pageSize", "@pageOrientation", "@tables",
				"@marginLeft", "@marginRight", "@marginTop", "@marginBottom",
				"@horizontalGutter", "@verticalGutter", "@labelWidth", "@labelHeight",
				"@elementCode"
			) as $vs_tag) {
				if (preg_match("!{$vs_tag}([^\n\n]+)!", $vs_template, $va_matches)) {
					$va_info[str_replace("@", "", $vs_tag)] = trim($va_matches[1]);
				} else {
					$va_info[str_replace("@", "", $vs_tag)] = null;
				}
			}
			$va_info['tables'] = preg_split("![,;]{1}!", $va_info['tables']);
			$va_info['path'] = $vs_template_path;

			ExternalCache::save($vs_cache_key, $va_info, 'PrintTemplateDetails');
			ExternalCache::save("{$vs_cache_key}_mtime", filemtime($vs_template_path), 'PrintTemplateDetails');
			
			return $va_info;
		}
		return null;
	}
	# ------------------------------------------------------------------
	/**
	 * Converts string quantity with units ($ps_value parameter) to a numeric quantity in
	 * points. Units are limited to inches, centimeters, millimeters, pixels and points as
	 * this function is primarily used to switch between units used when generating PDFs.
	 *
	 * @param $ps_value string The value to convert. Valid units are in, cm, mm, px and p. If units are invalid or omitted points are assumed.
	 * @param $pa_options array Options include:
	 *		dpi = dots-per-inch factor to use when converting physical units (in, cm, etc.) to points [Default is 72dpi]
	 *		ppi = synonym for dpi option
	 * @return int Converted measurement in points.
	 */
	function caConvertMeasurementToPoints($ps_value, $pa_options=null) {
		global $g_print_measurement_cache;

		if (isset($g_print_measurement_cache[$ps_value])) { return $g_print_measurement_cache[$ps_value]; }

		if (!preg_match("/^([\d\.]+)[ ]*([A-Za-z]*)$/", $ps_value, $va_matches)) {
			return $g_print_measurement_cache[$ps_value] = $ps_value;
		}
		
		$vn_dpi = caGetOption('dpi', $pa_options, caGetOption('ppi', $pa_options, 72));

		switch(strtolower($va_matches[2])) {
			case 'in':
				$ps_value_in_points = $va_matches[1] * $vn_dpi;
				break;
			case 'cm':
				$ps_value_in_points = $va_matches[1] * ($vn_dpi/2.54);
				break;
			case 'mm':
				$ps_value_in_points = $va_matches[1] * ($vn_dpi/24.4);
				break;
			case '':
			case 'px':
			case 'p':
				$ps_value_in_points = $va_matches[1];
				break;
			default:
				$ps_value_in_points = $ps_value;
				break;
		}

		return $g_print_measurement_cache[$ps_value] = $ps_value_in_points;
	}
	# ------------------------------------------------------------------
	/**
	 * Converts string quantity with units ($ps_value parameter) to a numeric quantity in
	 * the units specified by the $ps_units parameter. Units are limited to inches, centimeters, millimeters, pixels and points as
	 * this function is primarily used to switch between units used when generating PDFs.
	 *
	 * @param $ps_value string The value to convert. Valid units are in, cm, mm, px and p. If units are invalid or omitted points are assumed.
	 * @param $ps_units string A valid measurement unit: in, cm, mm, px, p (inches, centimeters, millimeters, pixels, points) respectively.
	 *
	 * @return int Converted measurement. If the output units are omitted or otherwise not valid, pixels are assumed.
	 */
	function caConvertMeasurement($ps_value, $ps_units) {
		$vn_in_points = caConvertMeasurementToPoints($ps_value);
		
		if (!preg_match("/^([\d\.]+)[ ]*([A-Za-z]*)$/", $ps_value, $va_matches)) {
			return $vn_in_points;
		}
		
		switch(strtolower($ps_units)) {
			case 'in':
				return $vn_in_points/72;
				break;
			case 'cm':
				return $vn_in_points/28.346;
				break;
			case 'mm':
				return $vn_in_points/2.8346;
				break;
			default:
			case 'px':
			case 'p':
				return $vn_in_points;
				break;
		}
	}
	# ------------------------------------------------------------------
	/**
	 * Converts string quantity with units ($ps_value parameter) to a numeric quantity in
	 * the units specified by the $ps_units parameter. Units are limited to inches, centimeters, millimeters, pixels and points as
	 * this function is primarily used to switch between units used when generating PDFs.
	 *
	 * @param $ps_value string The value to convert. Valid units are in, cm, mm, px and p. If units are invalid or omitted points are assumed.
	 * @param $ps_units string A valid measurement unit: in, cm, mm, px, p (inches, centimeters, millimeters, pixels, points) respectively.
	 *
	 * @return int Converted measurement. If the output units are omitted or otherwise not valid, pixels are assumed.
	 */
	function caParseMeasurement($ps_value, $pa_options=null) {
		if (!preg_match("/^([\d\.]+)[ ]*([A-Za-z]*)$/", $ps_value, $va_matches)) {
			return null;
		}

		switch(strtolower($va_matches[2])) {
			case 'in':
			case 'cm':
			case 'mm':
			case 'px':
			case 'p':
				return array('value' => $va_matches[1], 'units' => $va_matches[2]);
				break;
			default:
				return null;
				break;
		}

	}
	# ------------------------------------------------------------------
	/**
	 *
	 */
	function caGenerateBarcode($ps_value, $pa_options=null) {
		$ps_barcode_type = caGetOption('type', $pa_options, 'code128', array('forceLowercase' => true));
		$pn_barcode_height = caConvertMeasurementToPoints(caGetOption('height', $pa_options, '9px'));

		$vs_tmp = null;
		switch($ps_barcode_type) {
			case 'qr':
			case 'qrcode':
				$vs_tmp = tempnam(caGetTempDirPath(), 'caQRCode');
				$vs_tmp2 = tempnam(caGetTempDirPath(), 'caQRCodeResize');

				if (!defined('QR_LOG_DIR')) { define('QR_LOG_DIR', false); }

				if (($pn_barcode_height < 1) || ($pn_barcode_height > 8)) {
					$pn_barcode_height = 1;
				}
				QRcode::png($ps_value, "{$vs_tmp}.png", QR_ECLEVEL_H, $pn_barcode_height);
				return $vs_tmp;
				break;
			case 'code128':
			case 'code39':
			case 'ean13':
			case 'int25':
			case 'postnet':
			case 'upca':
				$o_barcode = new Barcode();
				$vs_tmp = tempnam(caGetTempDirPath(), 'caBarCode');
				if(!($va_dimensions = $o_barcode->draw($ps_value, "{$vs_tmp}.png", $ps_barcode_type, 'png', $pn_barcode_height))) { return null; }
				return $vs_tmp;
				break;
			default:
				// invalid barcode
				break;
		}

		return null;
	}
	# -------------------------------------------------------
	/**
	 *
	 */
	function caParseBarcodeViewTag($ps_tag, $po_view, $po_result, $pa_options=null) {
		$vs_tmp = null;
		if (substr($ps_tag, 0, 7) == 'barcode') {
			$o_barcode = new Barcode();

			// got a barcode
			$va_bits = explode(":", $ps_tag);
			array_shift($va_bits); // remove "barcode" identifier
			$vs_type = array_shift($va_bits);
			if (is_numeric($va_bits[0])) {
				$vn_size = (int)array_shift($va_bits);
				$vs_template = join(":", $va_bits);
			} else {
				$vn_size = 16;
				$vs_template = join(":", $va_bits);
			}

			$vs_tmp = caGenerateBarcode($po_result->getWithTemplate($vs_template, $pa_options), array('type' => $vs_type, 'height' => $vn_size));

			$po_view->setVar($ps_tag, "<img src='{$vs_tmp}.png'/>");
		}
		return $vs_tmp;
	}
	# ------------------------------------------------------------------
	/**
	 *
	 */
	function caDoPrintViewTagSubstitution($po_view, $po_result, $ps_template_path, $pa_options=null) {
		return caDoTemplateTagSubstitution($po_view, $po_result, $ps_template_path, ['render' => false, 'barcodes' => true, 'clearVars' => true]);
	}
	# ---------------------------------------
	/** 
	 *
	 */
	function caPrintLabels($po_view, $po_result, $ps_title) {
		try {
			$po_view->setVar('title', $ps_title);
			
		//vs_template_identifier
			// render labels
			$vn_width = 				caConvertMeasurement(caGetOption('labelWidth', $va_template_info, null), 'mm');
			$vn_height = 				caConvertMeasurement(caGetOption('labelHeight', $va_template_info, null), 'mm');
			
			$vn_top_margin = 			caConvertMeasurement(caGetOption('marginTop', $va_template_info, null), 'mm');
			$vn_bottom_margin = 		caConvertMeasurement(caGetOption('marginBottom', $va_template_info, null), 'mm');
			$vn_left_margin = 			caConvertMeasurement(caGetOption('marginLeft', $va_template_info, null), 'mm');
			$vn_right_margin = 			caConvertMeasurement(caGetOption('marginRight', $va_template_info, null), 'mm');
			
			$vn_horizontal_gutter = 	caConvertMeasurement(caGetOption('horizontalGutter', $va_template_info, null), 'mm');
			$vn_vertical_gutter = 		caConvertMeasurement(caGetOption('verticalGutter', $va_template_info, null), 'mm');
			
			$va_page_size =				PDFRenderer::getPageSize(caGetOption('pageSize', $va_template_info, 'letter'), 'mm', caGetOption('pageOrientation', $va_template_info, 'portrait'));
			$vn_page_width = $va_page_size['width']; $vn_page_height = $va_page_size['height'];
			
			$vn_label_count = 0;
			$vn_left = $vn_left_margin;
			
			$vn_top = $vn_top_margin;
			
			$vs_content = $this->render("pdfStart.php");
			
			
			$va_defined_vars = array_keys($po_view->getAllVars());		// get list defined vars (we don't want to copy over them)
			$va_tag_list = $this->getTagListForView($va_template_info['path']);				// get list of tags in view
			
			$va_barcode_files_to_delete = [];
			
			$vn_page_count = 0;
			while($po_result->nextHit()) {
				$va_barcode_files_to_delete = array_merge($va_barcode_files_to_delete, caDoPrintViewTagSubstitution($po_view, $po_result, $va_template_info['path'], array('checkAccess' => $this->opa_access_values)));
				
				$vs_content .= "<div style=\"{$vs_border} position: absolute; width: {$vn_width}mm; height: {$vn_height}mm; left: {$vn_left}mm; top: {$vn_top}mm; overflow: hidden; padding: 0; margin: 0;\">";
				$vs_content .= $this->render($va_template_info['path']);
				$vs_content .= "</div>\n";
				
				$vn_label_count++;
				
				$vn_left += $vn_vertical_gutter + $vn_width;
				
				if (($vn_left + $vn_width) > $vn_page_width) {
					$vn_left = $vn_left_margin;
					$vn_top += $vn_horizontal_gutter + $vn_height;
				}
				if (($vn_top + $vn_height) > (($vn_page_count + 1) * $vn_page_height)) {
					
					// next page
					if ($vn_label_count < $po_result->numHits()) { $vs_content .= "<div class=\"pageBreak\">&nbsp;</div>\n"; }
					$vn_left = $vn_left_margin;
						
					switch($vs_renderer) {
						case 'PhantomJS':
						case 'wkhtmltopdf':
							// WebKit based renderers (PhantomJS, wkhtmltopdf) want things numbered relative to the top of the document (Eg. the upper left hand corner of the first page is 0,0, the second page is 0,792, Etc.)
							$vn_page_count++;
							$vn_top = ($vn_page_count * $vn_page_height) + $vn_top_margin;
							break;
						case 'domPDF':
						default:
							// domPDF wants things positioned in a per-page coordinate space (Eg. the upper left hand corner of each page is 0,0)
							$vn_top = $vn_top_margin;								
							break;
					}
				}
			}
			
			$vs_content .= $this->render("pdfEnd.php");
			
			
			
			caExportAsPDF($po_view, $vs_template_identifier, caGetOption('filename', $va_template_info, 'labels.pdf'), []);

			$vb_printed_properly = true;
			
			foreach($va_barcode_files_to_delete as $vs_tmp) { @unlink($vs_tmp); @unlink("{$vs_tmp}.png");}
			
		} catch (Exception $e) {
			foreach($va_barcode_files_to_delete as $vs_tmp) { @unlink($vs_tmp); @unlink("{$vs_tmp}.png");}
			
			$vb_printed_properly = false;
			$this->postError(3100, _t("Could not generate PDF"),"BaseFindController->PrintSummary()");
		}
	}
	# ----------------------------------------
	/**
	 *
	 */
	function caGetReportImageTag($po_request, $pa_attributes=null, $pa_options=null) {
		$vs_attr = _caHTMLMakeAttributeString($pa_attributes);
		if($po_request->config->get('report_img') && file_exists($po_request->getThemeDirectoryPath()."/assets/pawtucket/graphics/".$po_request->config->get('report_img'))) {
			return "<img src='".$po_request->getThemeDirectoryPath()."/assets/pawtucket/graphics/".$po_request->config->get('report_img')."' {$vs_attr}/>";
		}
		return '';
	}
	# ----------------------------------------
	