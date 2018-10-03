<?php

use SilverStripe\Assets\File;
use SilverStripe\Control\HTTP;
use SilverStripe\Security\Member;
use SilverStripe\Core\Environment;
use SilverStripe\Security\Security;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Security\Permission;
use SilverStripe\Versioned\Versioned;

/**
 * A simple controller
 *
 */
class LegalFilesController extends Controller
{

    public function init()
    {
        parent::init();

        if (!Member::currentUserID()) {
            Security::permissionFailure();
        }
    }

    /**
     * Output file to user.
     * Send file content to browser for download progressively.
     */
    public function index()
    {
        $memberID = Member::currentUserID();

        $ID = $this->request->getVar('id');
        $filePath = $this->request->getVar('file');
        $inline = $this->request->getVar('inline');

        if ($ID) {
            //@link https://docs.silverstripe.org/en/4/developer_guides/model/versioning/
            $fileObj = Versioned::get_by_stage(File::class, Versioned::DRAFT)->byID($ID);
            $fileAssetPath = $fileObj->Filename;
        } else {
            $fileAssetPath = substr($filePath, stripos($filePath, 'assets'));
            $fileObj = File::get()->filter(array('Filename' => $fileAssetPath))->first();
        }

        if (!$fileObj) {
            return $this->httpError(404, 'File not found');
        }

        if ($fileObj->MemberID != $memberID && !Permission::check('CMS_ACCESS_LegalFilesAdmin')) {
            return $this->httpError(403, 'Forbidden');
        }

        $fullPath = null;
        if ($fileObj->hasMethod('getFullPath')) {
            $fullPath = $fileObj->getFullPath();
        }
        $fileData = $fileObj->getString();
        $fileName = $fileObj->Name;

        $disposition = 'attachment'; // or inline
        $mimeType = 'application/octet-stream';
        if ($inline && $fullPath) {
            $disposition = 'inline';
            // should be application/pdf for pdfs
            $mimeType = HTTP::get_mime_type($fullPath);
        }

        Environment::increaseTimeLimitTo();

        header('Content-Type: ' . $mimeType);
        header("Content-Transfer-Encoding: binary");
        header("Content-Disposition: $disposition; filename=\"$fileName\"");
        header("Accept-Ranges: bytes");
        header("Pragma: public");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        session_write_close();

        // return HTTPRequest::send_file($fileData, $fileName, $mimeType);
        echo $fileData;
        // readfile($fullPath);
        exit();
    }
}
