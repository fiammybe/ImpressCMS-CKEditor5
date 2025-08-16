<?php
/**
 * CKEditor 5 adapter for ImpressCMS
 *
 * @copyright	The ImpressCMS Project http://www.impresscms.org/
 * @license		http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License (GPL)
 * @package		editors
 * @since		2.0
 * @author		Modified by: Steve K <skenow@impresscms.org>
 */
defined("ICMS_ROOT_PATH") || die("Root path not defined");

class icmsFormCKEditor5 extends icms_form_elements_Textarea {
	var $rootpath = "";
	var $_language = _LANGCODE;
	var $_width = "100%";
	var $_height = "400px";
	var $_editorConfig = array();

	/**
	 * Constructor
	 *
	 * @param	array   $configs  Editor Options
	 * @param	bool 	$checkCompatible  true - return false on failure
	 */
	public function __construct($configs, $checkCompatible = false) {
		$current_path = __DIR__;
		if (DIRECTORY_SEPARATOR != "/") {
			$current_path = str_replace(strpos($current_path, "\\\\", 2) ? "\\\\" : DIRECTORY_SEPARATOR, "/", $current_path);
		}
		$docroot = pathinfo($_SERVER['DOCUMENT_ROOT']);
		$homepath = $docroot['dirname'] . DIRECTORY_SEPARATOR . $docroot['basename'];
		$this->rootpath = str_replace($homepath, '', $current_path) . '/';

		if (is_array($configs)) {
			$vars = array_keys(get_object_vars($this));
			foreach($configs as $key => $val) {
				if (in_array("_" . $key, $vars)) {
					$this->{"_" . $key} = $val;
				} else {
					$this->_editorConfig[$key] = $val;
				}
			}
		}

		parent::__construct(@$this->_caption, @$this->_name, @$this->_value);
		parent::setExtra("style='width: " . $this->_width . "; height: " . $this->_height . ";'");
	}

	/**
	 * get language
	 *
	 * @return	string
	 */
	protected function getLanguage() {
		$language = str_replace('_', '-', strtolower($this->_language));
		// CKEditor 5 uses ISO language codes like 'en', 'fr', 'de', etc.
		// Extract the first part before the hyphen if it exists
		if (strpos($language, '-') !== false) {
			$parts = explode('-', $language);
			$language = $parts[0];
		}
		return $language;
	}

	/**
	 * Gets the fonts for CKEditor
	 **/
	protected function getFonts() {
		if (empty($this->_editorConfig["fonts"]) && defined("_ICMS_EDITOR_CKEDITOR_FONTLIST")) {
			$this->_editorConfig["fonts"] = constant("_ICMS_EDITOR_CKEDITOR_FONTLIST");
		}

		return @$this->_editorConfig["fonts"];
	}

	/**
	 * prepare HTML for output
	 * @return	string    $ret    HTML
	 */
	public function render() {
		global $xoTheme;

		// Determine toolbar based on user permissions
		$toolbar = "Basic";
		if (is_object(icms::$user)) {
			$toolbar = "Normal";
			if (is_object(icms::$module)) {
				if (icms::$user->isAdmin(icms::$module->getVar('mid'))) {
					$toolbar = "Full";
				}
			}
		}

		// Define toolbar configurations
		$toolbarConfig = array(
			'Basic' => '["heading", "bold", "italic", "link", "bulletedList", "numberedList", "undo", "redo"]',
			'Normal' => '["heading", "bold", "italic", "link", "bulletedList", "numberedList", "blockQuote", "insertTable", "undo", "redo"]',
			'Full' => '["heading", "bold", "italic", "underline", "strikethrough", "link", "bulletedList", "numberedList", "blockQuote", "insertTable", "mediaEmbed", "undo", "redo", "alignment", "fontColor", "fontBackgroundColor", "findAndReplace", "sourceEditing"]'
		);

		// Add CKEditor 5 from CDN - using Classic Editor build (latest stable version)
		$ret = $xoTheme->addScript("https://cdn.ckeditor.com/ckeditor5/41.0.0/classic/ckeditor.js", array('type' => 'text/javascript'), '');

		// Add the custom initialization script
		$ret .= $xoTheme->addScript(ICMS_URL . '/editors/CKeditor5/js/ckeditor5-init.js', array('type' => 'text/javascript'), '');

		// Get the actual ID of the textarea
		$textareaId = $this->getName();

		// Debug mode - set to true to enable console logging for troubleshooting
		$debugMode = true;

		// Add initialization call with configuration
		$ret .= $xoTheme->addScript('', array('type' => 'text/javascript'),
			'document.addEventListener("DOMContentLoaded", function() {
				// Initialize CKEditor 5 with configuration
				initCKEditor5({
					textareaId: "' . $textareaId . '",
					toolbar: ' . $toolbarConfig[$toolbar] . ',
					language: "' . $this->getLanguage() . '",
					imageBrowserUrl: "' . ICMS_URL . '/editors/CKeditor5/imagebrowser.php",
					debug: ' . ($debugMode ? 'true' : 'false') . '
				});
			});');

		// Render the textarea
		$ret .= parent::render();

		return $ret;
	}

	/**
	 * Check if compatible
	 *
	 * @return  bool
	 */
	protected function isCompatible() {
		// CKEditor 5 is loaded from CDN, so we just need to check if the integration file exists
		return true;
	}
}
