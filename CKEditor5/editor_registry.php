<?php
/**
 * CKEditor 5 adapter for ImpressCMS
 *
 * @copyright	The ImpressCMS Project http://www.impresscms.org/
 * @license	http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License (GPL)
 * @package	editors
 * @since	2.0
 * @author	Modified by: Steve K <skenow@impresscms.org>
 */
global $icmsConfig;

$current_path = __FILE__;
if (DIRECTORY_SEPARATOR != "/") $current_path = str_replace(strpos($current_path, "\\\\", 2 ) ? "\\\\" : DIRECTORY_SEPARATOR, "/", $current_path);
$root_path = dirname($current_path);

$icmsConfig['language'] = preg_replace("/[^a-z0-9_\-]/i", "", $icmsConfig['language']);
if (!@include_once $root_path . "/language/" . $icmsConfig['language'] . ".php") {
	include_once $root_path . "/language/english.php";
}

return $config = array(
		"name"	=>	"CKEditor 5",
		"class"	=>	"icmsFormCKEditor5",
		"file"	=>	$root_path . "/formCkeditor5.php",
		"title"	=>	_ICMS_EDITOR_CKEDITOR5,
		"order"	=>	3,
		"nohtml"	=>	0
	);
