<?php
/**
 * CKEditor 5 upload adapter for ImpressCMS
 *
 * @copyright	The ImpressCMS Project http://www.impresscms.org/
 * @license		http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License (GPL)
 * @package		editors
 * @since		2.0
 */
if (file_exists('../../mainfile.php')) include_once '../../mainfile.php';
if (!defined('ICMS_ROOT_PATH')) die("ImpressCMS root path not defined");

// This file handles file uploads for CKEditor 5
// It returns JSON response compatible with CKEditor 5 upload adapter

// Check if user is logged in and has permissions
if (!is_object(icms::$user)) {
    $response = array(
        'uploaded' => 0,
        'error' => array(
            'message' => 'You must be logged in to upload files'
        )
    );
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Get the default image category
$imgcat_handler = icms::handler('icms_image_category');
$criteria = new icms_db_criteria_Compo();
$criteria->add(new icms_db_criteria_Item('imgcat_storetype', 'file'));
$criteria->setSort('imgcat_id');
$criteria->setOrder('ASC');
$imgcat = $imgcat_handler->getObjects($criteria, false, true);

if (empty($imgcat)) {
    $response = array(
        'uploaded' => 0,
        'error' => array(
            'message' => 'No valid image category found'
        )
    );
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Get the first available image category
$imgcat_id = array_keys($imgcat)[0];
$imagecategory = $imgcat_handler->get($imgcat_id);
$categ_path = $imgcat_handler->getCategFolder($imagecategory);
$categ_url = $imgcat_handler->getCategFolder($imagecategory, 1, 'url');

// Handle file upload
if (isset($_FILES['upload']) && !empty($_FILES['upload']['name'])) {
    $uploader = new icms_file_MediaUploadHandler(
        $categ_path, 
        array('image/gif', 'image/jpeg', 'image/pjpeg', 'image/x-png', 'image/png', 'image/bmp'), 
        $imagecategory->getVar('imgcat_maxsize'), 
        $imagecategory->getVar('imgcat_maxwidth'), 
        $imagecategory->getVar('imgcat_maxheight')
    );
    $uploader->setPrefix('img');
    
    if ($uploader->fetchMedia($_FILES['upload']['name'])) {
        if (!$uploader->upload()) {
            $response = array(
                'uploaded' => 0,
                'error' => array(
                    'message' => $uploader->getErrors()
                )
            );
        } else {
            // Save to database
            $image_handler = icms::handler('icms_image');
            $image = $image_handler->create();
            $image->setVar('image_name', $uploader->getSavedFileName());
            $image->setVar('image_nicename', pathinfo($_FILES['upload']['name'], PATHINFO_FILENAME));
            $image->setVar('image_mimetype', $uploader->getMediaType());
            $image->setVar('image_created', time());
            $image->setVar('image_display', 1);
            $image->setVar('image_weight', 0);
            $image->setVar('imgcat_id', $imgcat_id);
            
            if (!$image_handler->insert($image)) {
                $response = array(
                    'uploaded' => 0,
                    'error' => array(
                        'message' => 'Failed to save image information to database'
                    )
                );
            } else {
                // Success response
                $url = $categ_url . '/' . $uploader->getSavedFileName();
                $response = array(
                    'uploaded' => 1,
                    'fileName' => $uploader->getSavedFileName(),
                    'url' => $url
                );
            }
        }
    } else {
        $response = array(
            'uploaded' => 0,
            'error' => array(
                'message' => $uploader->getErrors()
            )
        );
    }
} else {
    $response = array(
        'uploaded' => 0,
        'error' => array(
            'message' => 'No file uploaded'
        )
    );
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;
