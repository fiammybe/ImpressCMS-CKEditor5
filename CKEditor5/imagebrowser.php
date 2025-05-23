<?php
/**
 * Images Manager - Image Browser for CKEditor 5
 *
 * Used to create an instance of the image manager in a popup window to use as plugin
 * Modified to work with CKEditor 5's messaging system
 *
 * @copyright The ImpressCMS Project http://www.impresscms.org/
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License (GPL)
 * @package core
 * @since 1.2
 * @author Rodrigo Pereira Lima (AKA TheRplima) <therplima@impresscms.org>
 */
if (file_exists('../../mainfile.php')) include_once '../../mainfile.php';
if (!defined('ICMS_ROOT_PATH')) die("ImpressCMS root path not defined");

use WideImage\WideImage as WideImage;

$icmsTpl = new icms_view_Tpl();

/* mirror this file - modules/system/admin/images/browser.php - and adjust for paths and URLS */

/*
 * GET variables
 * (str) op: list (default), listimg, addcat,
 * addfile, save, delfile, delfileok, cloneimg
 * save_edit_ok
 * (int) limit
 * (int) start
 * (int) imgcat_id
 * (int) image_id
 * (str) type
 * (str) target
 * (str) msg
 *
 * POST variables
 * (str) op
 * (int) limit
 * (int) start
 * (int) imgcat_id
 * (int) image_id
 * (str) query
 */

/* default values */
$op = 'list';
$fct = $query = $imgcat_name = $image_nicename = $imgcat_storetype = "";
$limit = 15;
$start = $imgcat_id = $image_id = 0;
$imgcat_pid = $imgcat_maxsize = $imgcat_maxwidth = $imgcat_maxheight = 0;
$imgcat_display = $imgcat_weight = 0;
$type = 'ibrow';
// $target = null;

$filter_get = array('limit' => 'int', 'start' => 'int', 'imgcat_id' => 'int', 'image_id' => 'int');

$filter_post = array('limit' => 'int',
	'start' => 'int',
	'imgcat_id' => 'int',
	'image_id' => 'int',
	'imgcat_pid' => 'int',
	'imgcat_maxsize' => 'int',
	'imgcat_maxwidth' => 'int',
	'imgcat_maxheight' => 'int',
	'imgcat_display' => 'int',
	'imgcat_weight' => 'int',
	'readgroup' => 'int',
	'writegroup' => 'int');

/* filter the user input */
if (!empty($_GET)) {
	// in places where strict mode is not used for checkVarArray, make sure filter_ vars are not overwritten
	if (isset($_GET['filter_post'])) unset($_GET['filter_post']);
	$clean_GET = icms_core_DataFilter::checkVarArray($_GET, $filter_get, false);
	extract($clean_GET);
}
if (!empty($_POST)) {
	$clean_POST = icms_core_DataFilter::checkVarArray($_POST, $filter_post, false);
	extract($clean_POST);
}

global $icmsConfig;
// Adding language files
icms_loadLanguageFile('system', 'images', true);

if (!is_object(icms::$user)) {
	$groups = array(XOOPS_GROUP_ANONYMOUS);
	$admin = false;
} else {
	$groups = &icms::$user->getGroups();
	$admin = (!icms::$user->isAdmin(1)) ? false : true;
}
if (!$admin) {
	exit(IMANAGER_NOPERM);
} else { // if ($admin) - start
	switch ($op) {
		case 'list':
			icmsPopupHeader();
			echo imanager_index($imgcat_id);
			icmsPopupFooter();
			break;

		case 'listimg':
			icmsPopupHeader();
			echo imanager_listimg($imgcat_id, $start);
			icmsPopupFooter();
			break;

		case 'addfile':
			imanager_addfile();
			break;

		case 'save':
			imanager_updateimage();
			break;

		case 'delfile':
			icmsPopupHeader();
			$image_handler = icms::handler('icms_image');
			$image = &$image_handler->get($image_id);
			$imgcat_handler = icms::handler('icms_image_category');
			$imagecategory = &$imgcat_handler->get($image->getVar('imgcat_id'));
			$src = '<img src="' . ICMS_MODULES_URL . "/system/admin/images/preview.php?file=" . $image->getVar('image_name') . '" title="' . $image->getVar('image_nicename') . '" /><br />';
			echo '<div style="margin:5px;" align="center">' . $src . '</div>';
			icms_core_Message::confirm(array('op' => 'delfileok', 'image_id' => $image_id, 'imgcat_id' => $imgcat_id, 'target' => $target, 'type' => $type), 'browser.php', _MD_RUDELIMG);
			icmsPopupFooter();
			break;

		case 'delfileok':
			imanager_delfileok($image_id, $imgcat_id);
			break;

		case 'cloneimg':
			imanager_clone();
			break;

		case 'save_edit_ok':
			$msg = !empty($msg) ? urldecode($msg) : '';
			redir($imgcat_id, filter_var($msg, FILTER_SANITIZE_ENCODED));
			break;

		case 'addcat':
			imanager_addcat();
			break;

		case 'selectimg':
			// Handle image selection for CKEditor 5
			$image_handler = icms::handler('icms_image');
			$image = &$image_handler->get($image_id);
			if (is_object($image)) {
				$imgcat_handler = icms::handler('icms_image_category');
				$imagecategory = &$imgcat_handler->get($image->getVar('imgcat_id'));
				$categ_url = $imgcat_handler->getCategFolder($imagecategory, 1, 'url');
				$url = $categ_url . '/' . $image->getVar('image_name');

				// Send the image URL back to the parent window using postMessage
				icmsPopupHeader();
				echo '<script type="text/javascript">
					window.opener.postMessage({imageUrl: "' . $url . '"}, "*");
					window.close();
				</script>';
				icmsPopupFooter();
			}
			break;

		default:
			break;
	}
}

/**
 * index for the image manager
 *
 * @return string the fetched and constructed template
 */
function imanager_index($imgcat_id = NULL) {
	global $icmsTpl, $icmsConfig, $target, $type, $limit;

	if (!is_object(icms::$user)) {
		$groups = array(ICMS_GROUP_ANONYMOUS);
		$admin = false;
	} else {
		$groups = &icms::$user->getGroups();
		$admin = (!icms::$user->isAdmin(1)) ? false : true;
	}

	if (!is_writable(ICMS_IMANAGER_FOLDER_PATH)) {
		icms_core_Message::warning(sprintf(_WARNINNOTWRITEABLE, str_ireplace(ICMS_ROOT_PATH, "", ICMS_IMANAGER_FOLDER_PATH)));
		echo '<br />';
	}

	if (!is_writable(ICMS_IMANAGER_FOLDER_PATH)) {
		icms_core_Message::warning(sprintf(_WARNINNOTWRITEABLE, str_ireplace(ICMS_ROOT_PATH, "", ICMS_IMANAGER_FOLDER_PATH)));
		echo '<br />';
	}

	$imgcat_handler = icms::handler('icms_image_category');

	$criteriaRead = new icms_db_criteria_Compo();
	if (is_array($groups) && !empty($groups)) {
		$criteriaTray = new icms_db_criteria_Compo();
		foreach ($groups as $gid) {
			$criteriaTray->add(new icms_db_criteria_Item('gperm_groupid', $gid), 'OR');
		}
		$criteriaRead->add($criteriaTray);
		$criteriaRead->add(new icms_db_criteria_Item('gperm_name', 'imgcat_read'));
		$criteriaRead->add(new icms_db_criteria_Item('gperm_modid', 1));
	}
	$criteriaRead->add(new icms_db_criteria_Item('imgcat_display', 1));
	$id = (!is_null($imgcat_id) ? $imgcat_id : 0);
	$criteriaRead->add(new icms_db_criteria_Item('imgcat_pid', $id));
	$imagecategorys = &$imgcat_handler->getObjects($criteriaRead);
	$criteriaWrite = new icms_db_criteria_Compo();
	if (is_array($groups) && !empty($groups)) {
		$criteriaWrite->add($criteriaTray);
		$criteriaWrite->add(new icms_db_criteria_Item('gperm_name', 'imgcat_write'));
		$criteriaWrite->add(new icms_db_criteria_Item('gperm_modid', 1));
	}
	$criteriaWrite->add(new icms_db_criteria_Item('imgcat_display', 1));
	$criteriaWrite->add(new icms_db_criteria_Item('imgcat_pid', $id));
	$imagecategorysWrite = &$imgcat_handler->getObjects($criteriaWrite);

	$icmsTpl->assign('lang_imanager_title', _IMGMANAGER);
	$icmsTpl->assign('lang_imanager_catid', _MD_IMAGECATID);
	$icmsTpl->assign('lang_imanager_catname', _MD_IMAGECATNAME);
	$icmsTpl->assign('lang_imanager_catmsize', _MD_IMAGECATMSIZE);
	$icmsTpl->assign('lang_imanager_catmwidth', _MD_IMAGECATMWIDTH);
	$icmsTpl->assign('lang_imanager_catmheight', _MD_IMAGECATMHEIGHT);
	$icmsTpl->assign('lang_imanager_catstype', _MD_IMAGECATSTYPE);
	$icmsTpl->assign('lang_imanager_catdisp', _MD_IMAGECATDISP);
	$icmsTpl->assign('lang_imanager_catautoresize', _MD_IMAGECATATUORESIZE);
	$icmsTpl->assign('lang_imanager_catweight', _MD_IMAGECATWEIGHT);
	$icmsTpl->assign('lang_imanager_catsubs', _MD_IMAGECATSUBS);
	$icmsTpl->assign('lang_imanager_catqtde', _MD_IMAGECATQTDE);
	$icmsTpl->assign('lang_imanager_catoptions', _MD_IMAGECATOPTIONS);

	$icmsTpl->assign('lang_imanager_cat_edit', _EDIT);
	$icmsTpl->assign('lang_imanager_cat_del', _DELETE);
	$icmsTpl->assign('lang_imanager_cat_listimg', _LIST);
	$icmsTpl->assign('lang_imanager_cat_submit', _SUBMIT);
	$icmsTpl->assign('lang_imanager_folder_not_writable', IMANAGER_FOLDER_NOT_WRITABLE);

	$icmsTpl->assign('lang_imanager_cat_addnewcat', _MD_ADDIMGCATBTN);
	$icmsTpl->assign('lang_imanager_cat_addnewimg', _MD_ADDIMGBTN);
	$icmsTpl->assign('lang_imanager_viewsubs', _MD_IMAGE_VIEWSUBS);

	$icmsTpl->assign('token', icms::$security->getTokenHTML());
	$icmsTpl->assign('catcount', count($imagecategorys));
	$icmsTpl->assign('writecatcount', count($imagecategorysWrite));
	$icmsTpl->assign('target', $target);
	$icmsTpl->assign('type', $type);
	$icmsTpl->assign('isAdmin', $admin);

	$icmsTpl->assign('imagecategorys', $imagecategorys);
	$icmsTpl->assign('admnav', adminNav($imgcat_id));

	$image_handler = icms::handler('icms_image');
	$count = $msize = $subs = array();
	$icmsTpl->assign('catcount', $catcount = count($imagecategorys));
	for ($i = 0; $i < $catcount; $i++ ) {
		$msize[$i] = icms_convert_size($imagecategorys[$i]->getVar('imgcat_maxsize'));
		$count[$i] = $image_handler->getCount(new icms_db_criteria_Item('imgcat_id', $imagecategorys[$i]->getVar('imgcat_id')));
		$criteriaRead = new icms_db_criteria_Compo();
		if (is_array($groups) && !empty($groups)) {
			$criteriaTray = new icms_db_criteria_Compo();
			foreach ($groups as $gid) {
				$criteriaTray->add(new icms_db_criteria_Item('gperm_groupid', $gid), 'OR');
			}
			$criteriaRead->add($criteriaTray);
			$criteriaRead->add(new icms_db_criteria_Item('gperm_name', 'imgcat_read'));
			$criteriaRead->add(new icms_db_criteria_Item('gperm_modid', 1));
		}
		$id = (!is_null($imgcat_id) ? $imgcat_id : 0);
		$criteriaRead->add(new icms_db_criteria_Item('imgcat_pid', $imagecategorys[$i]->getVar('imgcat_id')));
		$subs[$i] = count($imgcat_handler->getObjects($criteriaRead));
	}
	$scount = array();
	foreach ($subs as $k => $v) {
		$criteriaRead = new icms_db_criteria_Compo();
		if (is_array($groups) && !empty($groups)) {
			$criteriaTray = new icms_db_criteria_Compo();
			foreach ($groups as $gid) {
				$criteriaTray->add(new icms_db_criteria_Item('gperm_groupid', $gid), 'OR');
			}
			$criteriaRead->add($criteriaTray);
			$criteriaRead->add(new icms_db_criteria_Item('gperm_name', 'imgcat_read'));
			$criteriaRead->add(new icms_db_criteria_Item('gperm_modid', 1));
		}
		$id = (!is_null($imgcat_id) ? $imgcat_id : 0);
		$criteriaRead->add(new icms_db_criteria_Item('imgcat_pid', $imagecategorys[$k]->getVar('imgcat_id')));
		$ssubs = $imgcat_handler->getObjects($criteriaRead);
		$sc = 0;
		foreach ($ssubs as $id => $va) {
			$sc += $image_handler->getCount(new icms_db_criteria_Item('imgcat_id', $va->getVar('imgcat_id')));
		}
		$scount[$k] = $sc;
	}
	$icmsTpl->assign('msize', $msize);
	$icmsTpl->assign('count', $count);
	$icmsTpl->assign('subs', $subs);
	$icmsTpl->assign('scount', $scount);

	if (!empty($catcount)) {
		$form = new icms_form_Theme(_ADDIMAGE, 'image_form', 'browser.php', 'post', true);
		$form->setExtra('enctype="multipart/form-data"');
		$form->addElement(new icms_form_elements_Text(_IMAGENAME, 'image_nicename', 50, 255), true);
		$select = new icms_form_elements_Select(_IMAGECAT, 'imgcat_id');
		$select->addOptionArray($imgcat_handler->getCategList($groups, 'imgcat_write'));
		$form->addElement($select, true);
		$form->addElement(new icms_form_elements_File(_IMAGEFILE, 'image_file', 5000000));
		$form->addElement(new icms_form_elements_Text(_IMGWEIGHT, 'image_weight', 3, 4, 0));
		$form->addElement(new icms_form_elements_Radioyn(_IMGDISPLAY, 'image_display', 1, _YES, _NO));
		$form->addElement(new icms_form_elements_Hidden('op', 'addfile'));
		$form->addElement(new icms_form_elements_Hidden('target', $target));
		$form->addElement(new icms_form_elements_Hidden('type', $type));
		$tray = new icms_form_elements_Tray('', '');
		$tray->addElement(new icms_form_elements_Button('', 'img_button', _SUBMIT, 'submit'));
		$btn = new icms_form_elements_Button('', 'reset', _CANCEL, 'button');
		$btn->setExtra('onclick="document.getElementById(\'addimgform\').style.display = \'none\'; return false;"');
		$tray->addElement($btn);
		$form->addElement($tray);
		$icmsTpl->assign('addimgform', $form->render());
	}
	$form = new icms_form_Theme(_MD_ADDIMGCAT, 'imagecat_form', 'browser.php', 'post', true);
	$list = $imgcat_handler->getCategList($groups, 'imgcat_write');
	$sup = new icms_form_elements_Select(_MD_IMGCATPARENT, 'imgcat_pid', $id);
	$list[0] = '--------------------';
	ksort($list);
	$sup->addOptionArray($list);
	$form->addElement($sup);
	$form->addElement(new icms_form_elements_Text(_MD_IMGCATNAME, 'imgcat_name', 50, 255), true);
	$form->addElement(new icms_form_elements_select_Group(_MD_IMGCATRGRP, 'readgroup', true, ICMS_GROUP_ADMIN, 5, true));
	$form->addElement(new icms_form_elements_select_Group(_MD_IMGCATWGRP, 'writegroup', true, ICMS_GROUP_ADMIN, 5, true));
	$form->addElement(new icms_form_elements_Text(_IMGMAXSIZE, 'imgcat_maxsize', 10, 10, 50000));
	$form->addElement(new icms_form_elements_Text(_IMGMAXWIDTH, 'imgcat_maxwidth', 3, 4, 120));
	$form->addElement(new icms_form_elements_Text(_IMGMAXHEIGHT, 'imgcat_maxheight', 3, 4, 120));
	$form->addElement(new icms_form_elements_Text(_MD_IMGCATWEIGHT, 'imgcat_weight', 3, 4, 0));
	$form->addElement(new icms_form_elements_Radioyn(_MD_IMGCATDISPLAY, 'imgcat_display', 1, _YES, _NO));
	$storetype = new icms_form_elements_Radio(_MD_IMGCATSTRTYPE, 'imgcat_storetype', 'file');
	$storetype->setDescription('<span style="color:#ff0000;">' . _MD_STRTYOPENG . '</span>');
	$storetype->addOptionArray(array('file' => sprintf(_MD_ASFILE, str_ireplace(ICMS_ROOT_PATH, "", ICMS_IMANAGER_FOLDER_PATH) . '/foldername'), 'db' => _MD_INDB));
	$storetype->setExtra('onchange="actField(this.value, \'imgcat_foldername\');"');
	$form->addElement($storetype);
	$fname = new icms_form_elements_Text(_MD_IMGCATFOLDERNAME, 'imgcat_foldername', 50, 255, '');
	$fname->setDescription('<span style="color:#ff0000;">' . _MD_IMGCATFOLDERNAME_DESC . '<br />' . _MD_STRTYOPENG . '</span>');
	$form->addElement($fname, true);
	$form->addElement(new icms_form_elements_Hidden('op', 'addcat'));
	$form->addElement(new icms_form_elements_Hidden('target', $target));
	$form->addElement(new icms_form_elements_Hidden('type', $type));
	$tray1 = new icms_form_elements_Tray('', '');
	$tray1->addElement(new icms_form_elements_Button('', 'imgcat_button', _SUBMIT, 'submit'));
	$btn = new icms_form_elements_Button('', 'reset', _CANCEL, 'button');
	$btn->setExtra('onclick="document.getElementById(\'addcatform\').style.display = \'none\'; return false;"');
	$tray1->addElement($btn);
	$form->addElement($tray1);
	$icmsTpl->assign('addcatform', $form->render());

	return $icmsTpl->fetch('db:system_popup_imagemanager.html');
}

/**
 * The listing of the images for the image manager
 *
 * @param int $imgcat_id The imageCatID to get the listing for
 * @param int $start The start offset to get the listing for
 * @return string the fetched and construced template
 */
function imanager_listimg($imgcat_id, $start = 0) {
	global $icmsTpl, $target, $type, $query, $limit;

	if (!is_object(icms::$user)) {
		$groups = array(ICMS_GROUP_ANONYMOUS);
		$admin = false;
	} else {
		$groups = &icms::$user->getGroups();
		$admin = (!icms::$user->isAdmin(1)) ? false : true;
	}

	$query = !empty($query) ? $query : NULL;

	if ($imgcat_id <= 0) {
		redirect_header($_SERVER['SCRIPT_NAME'] . '?op=list&target=' . $target . '&type=' . $type, 1, '');
	}
	$imgcat_handler = icms::handler('icms_image_category');
	$imagecategory = &$imgcat_handler->get($imgcat_id);
	$categ_path = $imgcat_handler->getCategFolder($imagecategory);
	$categ_url = $imgcat_handler->getCategFolder($imagecategory, 1, 'url');
	if (!is_object($imagecategory)) {
		redirect_header($_SERVER['SCRIPT_NAME'] . '?op=list&target=' . $target . '&type=' . $type, 1);
	}

	$icmsTpl->assign('admnav', adminNav($imgcat_id, '/', 1));
	$icmsTpl->assign('lang_imanager_title', _IMGMANAGER);
	$icmsTpl->assign('lang_imanager_catmsize', _MD_IMAGECATMSIZE);
	$icmsTpl->assign('lang_imanager_catmwidth', _MD_IMAGECATMWIDTH);
	$icmsTpl->assign('lang_imanager_catmheight', _MD_IMAGECATMHEIGHT);
	$icmsTpl->assign('lang_imanager_catstype', _MD_IMAGECATSTYPE);
	$icmsTpl->assign('lang_imanager_catdisp', _MD_IMAGECATDISP);
	$icmsTpl->assign('lang_imanager_catsubs', _MD_IMAGECATSUBS);
	$icmsTpl->assign('lang_imanager_catqtde', _MD_IMAGECATQTDE);
	$icmsTpl->assign('lang_imanager_catoptions', _MD_IMAGECATOPTIONS);

	$icmsTpl->assign('lang_imanager_cat_edit', _EDIT);
	$icmsTpl->assign('lang_imanager_cat_clone', _CLONE);
	$icmsTpl->assign('lang_imanager_cat_del', _DELETE);
	$icmsTpl->assign('lang_imanager_cat_listimg', _LIST);
	$icmsTpl->assign('lang_imanager_cat_submit', _SUBMIT);
	$icmsTpl->assign('lang_imanager_cat_back', _BACK);
	$icmsTpl->assign('lang_imanager_cat_addimg', _ADDIMAGE);

	$icmsTpl->assign('lang_imanager_cat_addnewcat', _MD_ADDIMGCATBTN);
	$icmsTpl->assign('lang_imanager_cat_addnewimg', _MD_ADDIMGBTN);

	$icmsTpl->assign('cat_maxsize', icms_convert_size($imagecategory->getVar('imgcat_maxsize')));
	$icmsTpl->assign('cat_maxwidth', $imagecategory->getVar('imgcat_maxwidth'));
	$icmsTpl->assign('cat_maxheight', $imagecategory->getVar('imgcat_maxheight'));
	$icmsTpl->assign('cat_storetype', $imagecategory->getVar('imgcat_storetype'));
	$icmsTpl->assign('cat_display', $imagecategory->getVar('imgcat_display'));
	$icmsTpl->assign('cat_id', $imagecategory->getVar('imgcat_id'));

	$criteriaRead = new icms_db_criteria_Compo();
	if (is_array($groups) && !empty($groups)) {
		$criteriaTray = new icms_db_criteria_Compo();
		foreach ($groups as $gid) {
			$criteriaTray->add(new icms_db_criteria_Item('gperm_groupid', $gid), 'OR');
		}
		$criteriaRead->add($criteriaTray);
		$criteriaRead->add(new icms_db_criteria_Item('gperm_name', 'imgcat_read'));
		$criteriaRead->add(new icms_db_criteria_Item('gperm_modid', 1));
	}
	$criteriaRead->add(new icms_db_criteria_Item('imgcat_pid', $imagecategory->getVar('imgcat_id')));
	$subcats = $imgcat_handler->getObjects($criteriaRead);
	$subs = count($subcats);
	$icmsTpl->assign('cat_subs', $subs);

	$image_handler = icms::handler('icms_image');

	$criteriaRead = new icms_db_criteria_Compo();
	if (is_array($groups) && !empty($groups)) {
		$criteriaTray = new icms_db_criteria_Compo();
		foreach ($groups as $gid) {
			$criteriaTray->add(new icms_db_criteria_Item('gperm_groupid', $gid), 'OR');
		}
		$criteriaRead->add($criteriaTray);
		$criteriaRead->add(new icms_db_criteria_Item('gperm_name', 'imgcat_read'));
		$criteriaRead->add(new icms_db_criteria_Item('gperm_modid', 1));
	}
	$id = (!is_null($imgcat_id) ? $imgcat_id : 0);
	$criteriaRead->add(new icms_db_criteria_Item('imgcat_pid', $imagecategory->getVar('imgcat_id')));
	$ssubs = $imgcat_handler->getObjects($criteriaRead);
	$sc = 0;
	foreach ($ssubs as $id => $va) {
		$sc += $image_handler->getCount(new icms_db_criteria_Item('imgcat_id', $va->getVar('imgcat_id')));
	}
	$scount = $sc;
	$icmsTpl->assign('simgcount', $scount);

	$icmsTpl->assign('lang_imanager_img_preview', _PREVIEW);

	$icmsTpl->assign('lang_image_name', _IMAGENAME);
	$icmsTpl->assign('lang_image_mimetype', _IMAGEMIME);
	$icmsTpl->assign('lang_image_cat', _IMAGECAT);
	$icmsTpl->assign('lang_image_weight', _IMGWEIGHT);
	$icmsTpl->assign('lang_image_disp', _IMGDISPLAY);
	$icmsTpl->assign('lang_submit', _SUBMIT);
	$icmsTpl->assign('lang_cancel', _CANCEL);
	$icmsTpl->assign('lang_yes', _YES);
	$icmsTpl->assign('lang_no', _NO);
	$icmsTpl->assign('lang_search', _SEARCH);
	$icmsTpl->assign('lang_select', _SELECT);
	$icmsTpl->assign('lang_search_title', _QSEARCH);

	$icmsTpl->assign('lang_imanager_img_editor', 'DHTML Image Editor');

	$icmsTpl->assign('icms_root_path', ICMS_ROOT_PATH);
	$icmsTpl->assign('query', $query);
	$icmsTpl->assign('target', $target);
	$icmsTpl->assign('type', $type);

	$image_handler = icms::handler('icms_image');
	$criteria = new icms_db_criteria_Compo(new icms_db_criteria_Item('imgcat_id', $imgcat_id));
	if (!is_null($query)) {
		$criteria->add(new icms_db_criteria_Item('image_nicename', $query . '%', 'LIKE'));
	}
	$imgcount = $image_handler->getCount($criteria);
	$criteria->setStart($start);
	$criteria->setOrder('DESC');
	$criteria->setSort('image_weight');
	$criteria->setLimit($limit);
	$images = &$image_handler->getObjects($criteria, true, true);

	$icmsTpl->assign('imgcount', $imgcount);

	$arrimg = array();
	foreach (array_keys($images) as $i) {
		$arrimg[$i]['id'] = $images[$i]->getVar('image_id');
		$arrimg[$i]['name'] = $images[$i]->getVar('image_name');
		$arrimg[$i]['nicename'] = $images[$i]->getVar('image_nicename');
		$arrimg[$i]['mimetype'] = $images[$i]->getVar('image_mimetype');
		$arrimg[$i]['weight'] = $images[$i]->getVar('image_weight');
		$arrimg[$i]['display'] = $images[$i]->getVar('image_display');
		$arrimg[$i]['categ_id'] = $images[$i]->getVar('imgcat_id');
		$arrimg[$i]['display_nicename'] = icms_core_DataFilter::icms_substr($images[$i]->getVar('image_nicename'), 0, 20);

		$uniq = icms_random_str(5);

		if ($imagecategory->getVar('imgcat_storetype') == 'db') {
			$src = ICMS_MODULES_URL . "/system/admin/images/preview.php?file=" . $images[$i]->getVar('image_name') . '&resize=0';
			$img = WideImage::load($images[$i]->getVar('image_body'))->saveToFile(ICMS_IMANAGER_FOLDER_PATH . '/' . $images[$i]->getVar('image_name'));
			$arrimg[$i]['size'] = icms_convert_size(filesize(ICMS_IMANAGER_FOLDER_PATH . '/' . $images[$i]->getVar('image_name')));
			$img_info = WideImage::load(ICMS_IMANAGER_FOLDER_PATH . '/' . $images[$i]->getVar('image_name'));
			$arrimg[$i]['width'] = $img_info->getWidth();
			$arrimg[$i]['height'] = $img_info->getHeight();
			@unlink(ICMS_IMANAGER_FOLDER_PATH . '/' . $images[$i]->getVar('image_name'));
			$path = ICMS_IMANAGER_FOLDER_PATH . '/';
			$arrimg[$i]['lcode'] = '[img align=left id=' . $images[$i]->getVar('image_id') . ']' . $images[$i]->getVar('image_nicename') . '[/img]';
			$arrimg[$i]['code'] = '[img align=center id=' . $images[$i]->getVar('image_id') . ']' . $images[$i]->getVar('image_nicename') . '[/img]';
			$arrimg[$i]['rcode'] = '[img align=right id=' . $images[$i]->getVar('image_id') . ']' . $images[$i]->getVar('image_nicename') . '[/img]';
		} else {
			$url = (substr($categ_url, -1) != '/') ? $categ_url . '/' : $categ_url;
			$path = (substr($categ_path, -1) != '/') ? $categ_path . '/' : $categ_path;
			$src = $url . $images[$i]->getVar('image_name');
			$arrimg[$i]['size'] = icms_convert_size(filesize($path . $images[$i]->getVar('image_name')));
			$img_info = WideImage::load($path . $images[$i]->getVar('image_name'));
			$arrimg[$i]['width'] = $img_info->getWidth();
			$arrimg[$i]['height'] = $img_info->getHeight();
			$arrimg[$i]['lcode'] = '[img align=left]' . $url . $images[$i]->getVar('image_name') . '[/img]';
			$arrimg[$i]['code'] = '[img align=center]' . $url . $images[$i]->getVar('image_name') . '[/img]';
			$arrimg[$i]['rcode'] = '[img align=right]' . $url . $images[$i]->getVar('image_name') . '[/img]';
		}
		$arrimg[$i]['src'] = $src . '?' . time();
		$arrimg[$i]['url_src'] = str_replace(ICMS_URL, '', $src);
		$src_lightbox = ICMS_MODULES_URL . "/system/admin/images/preview.php?file=" . $images[$i]->getVar('image_name');
		$preview_url = '<a href="' . $src_lightbox . '" rel="lightbox[categ' . $images[$i]->getVar('imgcat_id') . ']" title="' . $images[$i]->getVar('image_nicename') . '"><img src="' . ICMS_IMAGES_SET_URL . '/actions/viewmag.png" alt="' . _PREVIEW . '" title="' . _PREVIEW . '" /></a>';
		$arrimg[$i]['preview_link'] = $preview_url;

		// Add select button for CKEditor 5
		$arrimg[$i]['select_link'] = '<a href="browser.php?op=selectimg&image_id=' . $images[$i]->getVar('image_id') . '" class="select-btn" title="' . _SELECT . '"><img src="' . ICMS_IMAGES_SET_URL . '/actions/button_ok.png" alt="' . _SELECT . '" title="' . _SELECT . '" /></a>';

		$extra_perm = array("image/jpeg", "image/jpeg", "image/png", "image/gif");
		if (in_array($images[$i]->getVar('image_mimetype'), $extra_perm)) {
			$arrimg[$i]['hasextra_link'] = 1;
			if (file_exists(ICMS_LIBRARIES_PATH . '/image-editor/image-edit.php')) {
				$arrimg[$i]['editor_link'] = 'window.open(\'' . ICMS_LIBRARIES_URL . '/image-editor/image-edit.php?image_id=' . $images[$i]->getVar('image_id') . '&uniq=' . $uniq . '&target=' . $target . '&type=' . $type . '\', \'icmsDHTMLImageEditor\',\'width=800,height=600,left=\'+parseInt(screen.availWidth/2-400)+\',top=\'+parseInt(screen.availHeight/2-350)+\',resizable=no,location=no,menubar=no,status=no,titlebar=no,scrollbars=no\'); return false;';
			} else {
				$arrimg[$i]['editor_link'] = '';
			}
		} else {
			$arrimg[$i]['hasextra_link'] = 0;
		}

		$list = $imgcat_handler->getList(array(), NULL, NULL, $imagecategory->getVar('imgcat_storetype'));
		$div = '';
		foreach ($list as $value => $name) {
			$sel = '';
			if ($value == $images[$i]->getVar('imgcat_id')) {
				$sel = ' selected="selected"';
			}
			$div .= '<option value="' . $value . '"' . $sel . '>' . $name . '</option>';
		}
		$arrimg[$i]['ed_selcat_options'] = $div;

		$arrimg[$i]['ed_token'] = icms::$security->getTokenHTML();
		$arrimg[$i]['clone_token'] = icms::$security->getTokenHTML();
	}

	$icmsTpl->assign('images', $arrimg);
	if ($imgcount > 0) {
		if ($imgcount > 15) {
			$nav = new icms_view_PageNav($imgcount, 15, $start, 'start', 'op=listimg&amp;imgcat_id=' . $imgcat_id . '&type=' . $type . '&target=' . $target);
			$icmsTpl->assign('pag', '<div class="img_list_info_panel" align="center">' . $nav->renderNav() . '</div>');
		} else {
			$icmsTpl->assign('pag', '');
		}
	} else {
		$icmsTpl->assign('pag', '');
	}
	$icmsTpl->assign('addimgform', showAddImgForm($imgcat_id));

	// Use custom template for CKEditor 5
	$icmsTpl->assign('icms_root_path', ICMS_ROOT_PATH);
	$icmsTpl->assign('template_path', dirname(__FILE__) . '/templates');
	return $icmsTpl->fetch('file:' . dirname(__FILE__) . '/templates/system_popup_imagemanager_imglist.html');
}

/**
 * This Method starts the parsing of the specified RDF File.
 * The File can be a local or a remote File.
 *
 * @access
 * @author
 * @param
 * @return
 * @see
 */
function imanager_addcat() {
	/* because we're using a function, the GET/POST variables are not in scope */
	global $imgcat_pid, $imgcat_name, $imgcat_maxsize;
	global $imgcat_maxwidth, $imgcat_maxheight, $imgcat_display;
	global $imgcat_weight, $imgcat_storetype, $imgcat_foldername;
	global $readgroup, $writegroup;

	if (!icms::$security->check()) {
		redirect_header($_SERVER['SCRIPT_NAME'] . '?op=list&target=' . $target . '&type=' . $type, 3, implode('<br />', icms::$security->getErrors()));
	}

	$imgcat_handler = icms::handler('icms_image_category');
	$imagecategory = &$imgcat_handler->create();
	$imagecategory->setVar('imgcat_pid', $imgcat_pid);
	$imagecategory->setVar('imgcat_name', $imgcat_name);
	$imagecategory->setVar('imgcat_maxsize', $imgcat_maxsize);
	$imagecategory->setVar('imgcat_maxwidth', $imgcat_maxwidth);
	$imagecategory->setVar('imgcat_maxheight', $imgcat_maxheight);
	$imgcat_display = empty($imgcat_display) ? 0 : 1;
	$imagecategory->setVar('imgcat_display', $imgcat_display);
	$imagecategory->setVar('imgcat_weight', $imgcat_weight);
	$imagecategory->setVar('imgcat_storetype', $imgcat_storetype);
	if ($imgcat_storetype == 'file') {
		$imagecategory->setVar('imgcat_foldername', $imgcat_foldername);
		$categ_path = $imgcat_handler->getCategFolder($imagecategory);
	}
	$imagecategory->setVar('imgcat_type', 'C');

	if (!file_exists($categ_path)) {
		if (!icms_core_Filesystem::mkdir($categ_path)) {
			redirect_header($_SERVER['SCRIPT_NAME'] . '?op=list&target=' . $target . '&type=' . $type, 1, _MD_FAILADDCAT);
		}
	}

	if (!$imgcat_handler->insert($imagecategory)) {
		redirect_header($_SERVER['SCRIPT_NAME'] . '?op=list&target=' . $target . '&type=' . $type, 1, _MD_FAILADDCAT);
	}

	$newid = $imagecategory->getVar('imgcat_id');
	$imagecategoryperm_handler = icms::handler('icms_member_groupperm');

	if (!isset($readgroup)) {
		$readgroup = array();
	}
	if (!in_array(ICMS_GROUP_ADMIN, $readgroup)) {
		array_push($readgroup, ICMS_GROUP_ADMIN);
	}

	foreach ($readgroup as $rgroup) {
		$imagecategoryperm = &$imagecategoryperm_handler->create();
		$imagecategoryperm->setVar('gperm_groupid', $rgroup);
		$imagecategoryperm->setVar('gperm_itemid', $newid);
		$imagecategoryperm->setVar('gperm_name', 'imgcat_read');
		$imagecategoryperm->setVar('gperm_modid', 1);
		$imagecategoryperm_handler->insert($imagecategoryperm);
		unset($imagecategoryperm);
	}

	if (!isset($writegroup)) {
		$writegroup = array();
	}

	if (!in_array(ICMS_GROUP_ADMIN, $writegroup)) {
		array_push($writegroup, ICMS_GROUP_ADMIN);
	}

	foreach ($writegroup as $wgroup) {
		$imagecategoryperm = &$imagecategoryperm_handler->create();
		$imagecategoryperm->setVar('gperm_groupid', $wgroup);
		$imagecategoryperm->setVar('gperm_itemid', $newid);
		$imagecategoryperm->setVar('gperm_name', 'imgcat_write');
		$imagecategoryperm->setVar('gperm_modid', 1);
		$imagecategoryperm_handler->insert($imagecategoryperm);
		unset($imagecategoryperm);
	}

	redirect_header($_SERVER['SCRIPT_NAME'] . '?op=list&target=' . $target . '&type=' . $type, 2, _MD_AM_DBUPDATED);
}

/**
 * Add file for the image manager
 */
function imanager_addfile() {
	/* because we are using a function, the GET/POST variables are not in scope */
	global $imgcat_id, $image_display, $image_weight, $image_nicename;

	if (!icms::$security->check()) {
		redirect_header($_SERVER['SCRIPT_NAME'] . '?op=list&target=' . $target . '&type=' . $type, 3, implode('<br />', icms::$security->getErrors()));
	}

	$imgcat_handler = icms::handler('icms_image_category');
	$imagecategory = &$imgcat_handler->get($imgcat_id);
	if (!is_object($imagecategory)) {
		redirect_header($_SERVER['SCRIPT_NAME'] . '?op=list&target=' . $target . '&type=' . $type, 1);
	}
	$categ_path = $imgcat_handler->getCategFolder($imagecategory);

	if ($imagecategory->getVar('imgcat_storetype') == 'db') {
		$updir = ICMS_IMANAGER_FOLDER_PATH;
	} else {
		$updir = $categ_path;
	}
	$uploader = new icms_file_MediaUploadHandler($updir, array('image/gif', 'image/jpeg', 'image/pjpeg', 'image/x-png', 'image/png', 'image/bmp'), $imagecategory->getVar('imgcat_maxsize'), $imagecategory->getVar('imgcat_maxwidth'), $imagecategory->getVar('imgcat_maxheight'));
	$uploader->setPrefix('img');
	$err = array();
	$ucount = count($_POST['xoops_upload_file']);
	for ($i = 0; $i < $ucount; $i++ ) {
		if ($uploader->fetchMedia($_POST['xoops_upload_file'][$i])) {
			if (!$uploader->upload()) {
				$err[] = $uploader->getErrors();
			} else {
				$image_handler = icms::handler('icms_image');
				$image = &$image_handler->create();
				$image->setVar('image_name', $uploader->getSavedFileName());
				$image->setVar('image_nicename', $image_nicename);
				$image->setVar('image_mimetype', $uploader->getMediaType());
				$image->setVar('image_created', time());
				$image_display = empty($image_display) ? 0 : 1;
				$image->setVar('image_display', $image_display);
				$image->setVar('image_weight', $image_weight);
				$image->setVar('imgcat_id', $imgcat_id);
				if ($imagecategory->getVar('imgcat_storetype') == 'db') {
					$fp = @fopen($uploader->getSavedDestination(), 'rb');
					$fbinary = @fread($fp, filesize($uploader->getSavedDestination()));
					@fclose($fp);
					$image->setVar('image_body', $fbinary, true);
					@unlink($uploader->getSavedDestination());
				}
				if (!$image_handler->insert($image)) {
					$err[] = sprintf(_FAILSAVEIMG, $image->getVar('image_nicename'));
				}
			}
		} else {
			$err[] = sprintf(_FAILFETCHIMG, $i);
			$err = array_merge($err, $uploader->getErrors(false));
		}
	}
	if (count($err) > 0) {
		icmsPopupHeader();
		icms_core_Message::error($err);
		icmsPopupFooter();
		exit();
	}
	if (isset($imgcat_id)) {
		$redir = '?op=listimg&imgcat_id=' . $imgcat_id . '&target=' . $target . '&type=' . $type;
	} else {
		$redir = '?op=list&target=' . $target . '&type=' . $type;
	}
	redirect_header($_SERVER['SCRIPT_NAME'] . $redir, 2, _MD_AM_DBUPDATED);
}

/**
 * Update an image for the image manager
 */
function imanager_updateimage() {
	/* because we are using a function, the GET/POST variables are not in scope */
	global $image_id, $image_display, $image_weight, $image_nicename, $imgcat_id;

	if (!icms::$security->check()) {
		redirect_header($_SERVER['SCRIPT_NAME'] . '?op=list&target=' . $target . '&type=' . $type, 3, implode('<br />', icms::$security->getErrors()));
	}

	$count = count($image_id);
	if ($count > 0) {
		$image_handler = icms::handler('icms_image');
		$error = array();
		for ($i = 0; $i < $count; $i++ ) {
			$image = &$image_handler->get($image_id[$i]);
			if (!is_object($image)) {
				$error[] = sprintf(_FAILGETIMG, $image_id[$i]);
				continue;
			}
			$image_display[$i] = empty($image_display[$i]) ? 0 : 1;
			$image->setVar('image_display', $image_display[$i]);
			$image->setVar('image_weight', $image_weight[$i]);
			$image->setVar('image_nicename', $image_nicename[$i]);
			if ($image->getVar('imgcat_id') != $imgcat_id[$i]) {
				$changedCat = true;
				$oldcat = (int) $image->getVar('imgcat_id');
			} else {
				$changedCat = false;
			}
			$image->setVar('imgcat_id', $imgcat_id[$i]);
			if (!$image_handler->insert($image)) {
				$error[] = sprintf(_FAILSAVEIMG, $image_id[$i]);
			}
			if ($changedCat) {
				$imgcat_handler = icms::handler('icms_image_category');
				$imagecategory = &$imgcat_handler->get($imgcat_id[$i]);
				$dest_categ_path = $imgcat_handler->getCategFolder($imagecategory);
				if ($imagecategory->getVar('imgcat_storetype') != 'db') {
					$oldimgcategory = &$imgcat_handler->get($oldcat);
					$src_categ_path = $imgcat_handler->getCategFolder($oldimgcategory);
					$src = $src_categ_path . '/' . $image->getVar('image_name');
					$dest = $dest_categ_path . '/' . $image->getVar('image_name');
					if (!copy($src, $dest)) {
						$error[] = sprintf(_FAILSAVEIMG, $image_id[$i]);
					}
				}
			}
		}
		if (count($error) > 0) {
			icmsPopupHeader();
			echo implode("<br />", $error) . "<br />";
			icmsPopupFooter();
			exit();
		}
	}
	if (isset($redir)) {
		$redir = '?op=listimg&imgcat_id=' . $redir . '&target=' . $target . '&type=' . $type;
	} else {
		$redir = '?op=list&target=' . $target . '&type=' . $type;
	}
	redirect_header($_SERVER['SCRIPT_NAME'] . $redir, 2, _MD_AM_DBUPDATED);
}

/**
 * Delete an image file after confirmation
 */
function imanager_delfileok($image_id, $redir = NULL) {
	global $target, $type;
	if (!icms::$security->check()) {
		redirect_header($_SERVER['SCRIPT_NAME'] . '?op=list&target=' . $target . '&type=' . $type, 3, implode('<br />', icms::$security->getErrors()));
	}
	$image_id = (int) $image_id;
	if ($image_id <= 0) {
		redirect_header($_SERVER['SCRIPT_NAME'] . '?op=list&target=' . $target . '&type=' . $type, 1);
	}
	$image_handler = icms::handler('icms_image');
	$image = &$image_handler->get($image_id);
	if (!is_object($image)) {
		redirect_header($_SERVER['SCRIPT_NAME'] . '?op=list&target=' . $target . '&type=' . $type, 1);
	}
	$imgcat_handler = icms::handler('icms_image_category');
	$imagecategory = &$imgcat_handler->get($image->getVar('imgcat_id'));
	$categ_path = $imgcat_handler->getCategFolder($imagecategory);
	if (!$image_handler->delete($image)) {
		icmsPopupHeader();
		icms_core_Message::error(sprintf(_MD_FAILDEL, $image->getVar('image_id')));
		icmsPopupFooter();
		exit();
	}
	@unlink($categ_path . '/' . $image->getVar('image_name'));
	if (isset($redir)) {
		$redir = '?op=listimg&imgcat_id=' . $redir . '&target=' . $target . '&type=' . $type;
	} else {
		$redir = '?op=list&target=' . $target . '&type=' . $type;
	}
	redirect_header($_SERVER['SCRIPT_NAME'] . $redir, 2, _MD_AM_DBUPDATED);
}

/**
 * Clone the file using the image manager
 */
function imanager_clone() {
	global $target, $type;

	if (!icms::$security->check()) {
		redirect_header($_SERVER['SCRIPT_NAME'] . '?op=list&target=' . $target . '&type=' . $type, 3, implode('<br />', icms::$security->getErrors()));
	}

	/* because we are using a function, the GET/POST variables are not in scope */
	global $imgcat_id, $image_nicename, $image_display, $image_weight, $image_id;

	$imgcat_handler = icms::handler('icms_image_category');
	$imagecategory = &$imgcat_handler->get($imgcat_id);
	if (!is_object($imagecategory)) {
		redirect_header($_SERVER['SCRIPT_NAME'] . '?op=list&target=' . $target . '&type=' . $type, 1);
	}
	$categ_path = $imgcat_handler->getCategFolder($imagecategory);

	$image_handler = icms::handler('icms_image');
	$image = &$image_handler->get($image_id);
	if (($ext = strrpos($image->getVar('image_name'), '.')) !== false) {
		$ext = strtolower(substr($image->getVar('image_name'), $ext + 1));
	}

	$imgname = 'img' . icms_random_str(12) . '.' . $ext;
	$newimg = &$image_handler->create();
	$newimg->setVar('image_name', $imgname);
	$newimg->setVar('image_nicename', $image_nicename);
	$newimg->setVar('image_mimetype', $image->getVar('image_mimetype'));
	$newimg->setVar('image_created', time());
	$newimg->setVar('image_display', $image_display);
	$newimg->setVar('image_weight', $image_weight);
	$newimg->setVar('imgcat_id', $imgcat_id);
	if ($imagecategory->getVar('imgcat_storetype') == 'db') {
		$src = ICMS_MODULES_URL . "/system/admin/images/preview.php?file=" . $image->getVar('image_name') . '&resize=0';
		$img = WideImage::load($image->getVar('image_body'))->saveToFile(ICMS_IMANAGER_FOLDER_PATH . '/' . $image->getVar('image_name'));
		$fp = @fopen(ICMS_IMANAGER_FOLDER_PATH . '/' . $image->getVar('image_name'), 'rb');
		$fbinary = @fread($fp, filesize(ICMS_IMANAGER_FOLDER_PATH . '/' . $image->getVar('image_name')));
		@fclose($fp);
		$newimg->setVar('image_body', $fbinary, true);
		@unlink(ICMS_IMANAGER_FOLDER_PATH . '/' . $image->getVar('image_name'));
	} else {
		if (!@copy($categ_path . '/' . $image->getVar('image_name'), $categ_path . '/' . $imgname)) {
			$msg = sprintf(_FAILSAVEIMG, $image->getVar('image_nicename'));
		}
	}
	if (!$image_handler->insert($newimg)) {
		$msg = sprintf(_FAILSAVEIMG, $newimg->getVar('image_nicename'));
	} else {
		$msg = _ICMS_DBUPDATED;
	}

	if (isset($imgcat_id)) {
		$redir = '?op=listimg&imgcat_id=' . $imgcat_id . '&target=' . $target . '&type=' . $type;
	} else {
		$redir = '?op=list&target=' . $target . '&type=' . $type;
	}
	redirect_header($_SERVER['SCRIPT_NAME'] . $redir, 2, $msg);
}

/**
 * The header piece for the popup
 * Just echoes the whole HTML
 */
function icmsPopupHeader() {
	global $icmsConfig;
	if (!headers_sent()) {
		header('Content-Type:text/html; charset=' . _CHARSET);
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");
	}
	echo "<!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Transitional//EN' 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd'>";
	echo '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="' . _LANGCODE . '" lang="' . _LANGCODE . '">
	<head>
	<meta http-equiv="content-type" content="text/html; charset=' . _CHARSET . '" />
	<meta http-equiv="content-language" content="' . _LANGCODE . '" />
	<title>' . htmlspecialchars($icmsConfig['sitename'], ENT_QUOTES) . ' - ' . _IMGMANAGER . '</title>
	<script type="text/javascript" src="' . ICMS_URL . '/include/xoops.js"></script>' . '<link rel="shortcut icon" type="image/ico" href="' . ICMS_URL . '/favicon.ico" />
	<link rel="icon" type="image/png" href="' . ICMS_URL . '/favicon.ico" />
	';
	if (defined('_ADM_USE_RTL') && _ADM_USE_RTL) {
		echo '<link rel="stylesheet" type="text/css" media="all" href="' . ICMS_URL . '/xoops_rtl.css" />';
		echo '<link rel="stylesheet" type="text/css" media="all" href="' . ICMS_MODULES_URL . '/system/style_rtl.css" />';
	} else {
		echo '<link rel="stylesheet" type="text/css" media="all" href="' . ICMS_URL . '/icms.css" />';
		echo '<link rel="stylesheet" type="text/css" media="all" href="' . ICMS_MODULES_URL . '/system/style.css" />';
	}

	# Adding necessary scripts
	icms::$preload->triggerEvent('adminHeader');
	icms::$preload->triggerEvent('adminBeforeFooter');

	echo "</head><body>";
	echo "<div id='containBodyCP'><br /><div id='bodyCP'>";
}

/**
 * The footer piece for the popup
 * Just echoes the whole HTML
 */
function icmsPopupFooter() {
	echo "</div>";
	echo '<div style="float: right; padding:11px;"><input type="button" id="cancel" name="cancel" value="' . _CLOSE . '" onclick="window.close();" /></div><br style="clear:both;" />';
	echo "</div></body></html>";
}

/**
 * Adding an image for the Image manager
 *
 * @return string The constructed HTML form for the adding of an image
 */
function showAddImgForm($imgcat_id) {
	global $target, $type;
	$imgcat_handler = icms::handler('icms_image_category');
	$form = new icms_form_Theme(_ADDIMAGE, 'image_form', 'browser.php', 'post', true);
	$form->setExtra('enctype="multipart/form-data"');
	$form->addElement(new icms_form_elements_Text(_IMAGENAME, 'image_nicename', 50, 255), true);
	$select = new icms_form_elements_Select(_IMAGECAT, 'imgcat_id', (int) $imgcat_id);
	$select->addOptionArray($imgcat_handler->getCategList());
	$form->addElement($select, true);
	$form->addElement(new icms_form_elements_File(_IMAGEFILE, 'image_file', 5000000));
	$form->addElement(new icms_form_elements_Text(_IMGWEIGHT, 'image_weight', 3, 4, 0));
	$form->addElement(new icms_form_elements_Radioyn(_IMGDISPLAY, 'image_display', 1, _YES, _NO));
	$form->addElement(new icms_form_elements_Hidden('imgcat_id', $imgcat_id));
	$form->addElement(new icms_form_elements_Hidden('op', 'addfile'));
	$form->addElement(new icms_form_elements_Hidden('target', $target));
	$form->addElement(new icms_form_elements_Hidden('type', $type));
	$tray = new icms_form_elements_Tray('', '');
	$tray->addElement(new icms_form_elements_Button('', 'img_button', _SUBMIT, 'submit'));
	$btn = new icms_form_elements_Button('', 'reset', _CANCEL, 'button');
	$btn->setExtra('onclick="document.getElementById(\'addimgform\').style.display = \'none\'; return false;"');
	$tray->addElement($btn);
	$form->addElement($tray);
	return $form->render();
}

/**
 * Shows the admin navigation
 *
 * @return string $ret the generated HTML for the admin navigation
 */
function adminNav($id = NULL, $separador = "/", $list = false, $style = "style='font-weight:bold'") {
	global $target, $type;

	$admin_url = $_SERVER['SCRIPT_NAME'] . '?target=' . $target . '&type=' . $type;
	if ($id === false) {
		return false;
	} else {
		if ($id > 0) {
			$id = (int) $id;
			$imgcat_handler = icms::handler('icms_image_category');
			$imagecategory = &$imgcat_handler->get($id);
			if ($imagecategory->getVar('imgcat_id') > 0) {
				if ($list) {
					$ret = $imagecategory->getVar('imgcat_name');
				} else {
					$ret = "<a href='" . $admin_url . "&imgcat_id=" . $imagecategory->getVar('imgcat_id') . "'>" . $imagecategory->getVar('imgcat_name') . "</a>";
				}
				if ($imagecategory->getVar('imgcat_pid') == 0) {
					return "<a href='" . $admin_url . "'>" . _MD_IMGMAIN . "</a> $separador " . $ret;
				} elseif ($imagecategory->getVar('imgcat_pid') > 0) {
					$ret = adminNav($imagecategory->getVar('imgcat_pid'), $separador) . " $separador " . $ret;
				}
			}
		} else {
			return false;
		}
	}
	return $ret;
}

/**
 * Redirect to the imagelisting showing a certain image catID and showing a certain message
 *
 * @param string $imgcat_id
 * @param string $msg
 */
function redir($imgcat_id, $msg = NULL) {
	global $target, $type;

	redirect_header($_SERVER['SCRIPT_NAME'] . '?op=listimg&imgcat_id=' . (int) $imgcat_id . '&target=' . $target . '&type=' . $type, 2, $msg);
}
