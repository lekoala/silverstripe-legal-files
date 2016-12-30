<?php

/**
 * A simple controller
 *
 */
class LegalFilesController extends Controller
{

    public static $allowed_actions = array();

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

        $filePath = $this->request->getVar('file');
        $inline = $this->request->getVar('inline');

        $fileAssetPath = substr($filePath, stripos($filePath, 'assets'));

        $fileObj = File::get()->filter(array('Filename' => $fileAssetPath))->first();

        if (!$fileObj) {
            return $this->httpError(404, 'File not found');
        }

        if ($fileObj->MemberID != $memberID && !Permission::check('CMS_ACCESS_LegalFilesAdmin')) {
            return $this->httpError(403, 'Forbidden');
        }

        $fullPath = $fileObj->getFullPath();
        $name = $fileObj->Name;

        $disposition = 'attachment'; // or inline
        $mimeType = 'application/octet-stream';
        if ($inline) {
            $disposition = 'inline';
            $mimeType = HTTP::get_mime_type($fullPath);
        }

        header('Content-Type: ' . $mimeType);
        header("Content-Transfer-Encoding: Binary");
        header("Content-Disposition: $disposition; filename=\"$name\"");
        header("Pragma: public");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");

        increase_time_limit_to(0);

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        session_write_close();

        readfile($fullPath);
        exit();
    }
}
