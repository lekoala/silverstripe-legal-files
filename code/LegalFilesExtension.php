<?php

use SilverStripe\Forms\Form;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Core\ClassInfo;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FieldList;
use SilverStripe\Security\Member;
use SilverStripe\Control\Director;
use SilverStripe\Forms\HiddenField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;

/**
 * Simply apply this extension to any type of record that use legal files
 *
 * @property \Company|\SilverStripe\Security\Member|\LegalFilesExtension $owner
 * @property ?string $LegalState
 * @property ?string $LegalStateChanged
 * @method \SilverStripe\ORM\DataList<\LegalFile> LegalFiles()
 */
class LegalFilesExtension extends DataExtension
{
    const STATE_NONE = 'none'; // no legal files attached
    const STATE_INVALID = 'invalid'; // has some expired, missing or invalid documents
    const STATE_VALID = 'valid'; // all documents are not expired

    private static $db = array(
        'LegalState' => "Enum('none,invalid,valid','none')",
        'LegalStateChanged' => "Datetime",
    );
    private static $has_many = array(
        'LegalFiles' => 'LegalFile',
    );
    protected static $legalFileObjects;

    public function IsLegalStateValid()
    {
        return $this->owner->LegalState == self::STATE_VALID;
    }

    public function IsLegalStateInvalid()
    {
        return $this->owner->LegalState == self::STATE_INVALID;
    }

    public function IsLegalStateNotSet()
    {
        return $this->owner->LegalState == self::STATE_NONE;
    }

    public function IsLegalStateSet()
    {
        return $this->owner->LegalState != self::STATE_NONE;
    }

    public function TranslatedLegalState()
    {
        switch ($this->owner->LegalState) {
            case self::STATE_NONE:
                return _t('LegalFilesExtension.STATE_NONE', 'No legal files');
            case self::STATE_INVALID:
                return _t('LegalFilesExtension.STATE_INVALID', 'Invalid');
            case self::STATE_VALID:
                return _t('LegalFilesExtension.STATE_VALID', 'Valid');
        }
    }

    public static function listLegalStates()
    {
        return [self::STATE_NONE, self::STATE_VALID, self::STATE_INVALID];
    }

    /**
     * A list of files with legal files
     * @return array
     */
    public static function listClassesWithLegalFile()
    {
        if (self::$legalFileObjects === null) {
            self::$legalFileObjects = array();
            $dataobjects = ClassInfo::subclassesFor(DataObject::class);
            array_shift($dataobjects);
            foreach ($dataobjects as $dataobject) {
                $singl = singleton($dataobject);

                if ($singl->hasExtension('LegalFilesExtension')) {
                    // Ignore custom classes
                    $parent = get_parent_class($dataobject);
                    if ($parent != DataObject::class) {
                        continue;
                    }
                    // Ignore pages
                    if ($singl instanceof Page && $dataobject !== 'Page') {
                        continue;
                    }
                    self::$legalFileObjects[$dataobject] = $dataobject;
                }
            }
        }
        return self::$legalFileObjects;
    }

    public function doSendLegalFilesReminder()
    {
        $res = $this->sendLegalFilesReminder($this->getAboutToExpireLegalFiles()->where('Reminded IS NULL'));

        if ($res) {
            return _t('LegalFile.REMINDER_SENT_SUCCESSFULLY', 'Reminder sent successfully');
        }
        return _t('LegalFile.FAILED_TO_SEND_REMINDER', 'Failed to send reminder');
    }

    /**
     * @return DataList|LegalFile[]
     */
    public function getAboutToExpireLegalFiles()
    {
        $days = LegalFile::config()->days_before_reminder;
        return $this->owner->LegalFiles()
            ->filter('ExpirationDate:LessThan', date('Y-m-d', strtotime('+' . $days . ' days')));
    }

    /**
     * Add a new legal file. Replace any existing from the same type.
     *
     * @param int $typeID
     * @param string $filename
     * @param string $extension
     * @return LegalFile
     */
    public function addNewLegalFile($typeID, $filename, $extension)
    {
        $lf = $this->getLegalFilesByType($typeID);
        $isNew = false;

        if ($lf->count() == 0) {
            $class = get_class($this->owner);
            $rel = $class . 'ID';
            $lf = new LegalFile;
            $lf->TypeID = $typeID;
            $lf->$rel = $this->owner->ID;
            $lf->write();

            $isNew = true;
        } else {
            $lf = $lf->first();

            // Reset fields
            $lf->Status = LegalFile::STATUS_WAITING;
            $lf->Reminded = null;
            $lf->Reviewed = null;
            $lf->ExpirationDate = null;
            $lf->Notes = null;

            // Delete old file
            if ($lf->FileID && $lf->File()->exists()) {
                $lf->File()->delete();
            }
        }

        $folderPath = LegalFile::config()->upload_folder;

        // Create a new File and attach it to LegalFile
        $file = new File();

        $base = Director::baseFolder();
        $parentFolder = Folder::find_or_make($folderPath);

        $name = 'Doc' . $lf->ID . '.' . strtolower($extension);

        $relativeFolderPath = $parentFolder ? $parentFolder->getRelativePath() : ASSETS_DIR . '/';
        $relativeFilePath = $relativeFolderPath . $name;

        $file->setFromLocalFile($filename);
        $file->OwnerID = Member::currentUserID();
        $file->ParentID = $parentFolder ? $parentFolder->ID : 0;
        // This is to prevent it from trying to rename the file
        $file->Name = basename($relativeFilePath);
        $file->write();
        $file->onAfterUpload();

        $lf->FileID = $file->ID;
        $lf->write();

        if (LegalFile::config()->admin_emails) {
            if ($isNew) {
                $template = 'LegalFilesNewDocumentEmail';
                $emailTitle = _t('LegalFilesNewDocumentEmail.SUBJECT', "A new legal document has been uploaded");
            } else {
                $template = 'LegalFilesDocumentReplacedEmail';
                $emailTitle = _t('LegalFilesDocumentReplacedEmail.SUBJECT', "A legal document has been replaced");
            }
            $email = LegalFileEmail::getEmail($lf, $emailTitle, $template);
            $email->setTo(LegalFileEmail::getAdminEmail());
            $email->send();
        }

        $this->owner->extend('onNewLegalFile', $lf);

        return $lf;
    }

    /**
     * List of legal files for a given type
     *
     * @return DataList|LegalFile[]
     */
    public function getLegalFilesByType($type)
    {
        if (is_object($type)) {
            $id = $type->ID;
        } else {
            $id = $type;
        }

        return $this->owner->LegalFiles()->filter('TypeID', $id);
    }

    /**
     * Send a reminder by email with a list of files
     *
     * @param array|LegalFile $files
     * @return array|boolean
     */
    public function sendLegalFilesReminder($files)
    {
        $emailTitle = _t('LegalFilesReminderEmail.SUBJECT', "Legal documents are about to be expired");

        // Create a list of files
        $filesHTML = '';
        foreach ($files as $file) {
            $filesHTML = '- ' . $file->Type()->getTitle() . '<br/>';
        }

        $templateData = ['Files' => $filesHTML];

        $email = LegalFileEmail::getEmail($this->owner, $emailTitle, 'LegalFilesReminderEmail', $templateData);
        $email->setTo($this->owner->Email);

        $res = $email->send();

        if ($res) {
            foreach ($files as $file) {
                $file->Reminded = date('Y-m-d H:i:s');
                $file->write();
            }
        }
        return $res;
    }

    public function forceLegalState($state)
    {
        if (!in_array($state, self::listLegalStates())) {
            throw new Exception("Invalid state $state");
        }
        $this->owner->LegalState = $state;
        $files = $this->owner->LegalFiles();
        switch ($state) {
            case self::STATE_NONE:
                foreach ($files as $file) {
                    $file->Status = "Waiting";
                    $file->write();
                    // $file->delete();
                }
            case self::STATE_INVALID:
                foreach ($files->filter('Status', 'Valid') as $file) {
                    $file->Status = "Waiting";
                    $file->write();
                    // $file->delete();
                }
            case self::STATE_VALID:
                foreach ($files->filter('Status', 'Invalid') as $file) {
                    $file->Status = "Waiting";
                    $file->write();
                    // $file->delete();
                }
        }
    }

    /**
     * Refresh legal state based on current legal files
     */
    public function refreshLegalState($writeIfChanged = false)
    {
        $files = $this->owner->LegalFiles();
        if ($files->count() == 0 && LegalFile::config()->default_none_state) {
            $this->owner->LegalState = self::STATE_NONE;
        } else {
            $state = self::STATE_VALID;
            /* @var $file LegalFile */
            foreach ($files as $file) {
                if ($file->IsInvalid()) {
                    $state = self::STATE_INVALID;
                }
            }
            $this->owner->LegalState = $state;
            $this->owner->LegalStateChanged = date('Y-m-d H:i:s');
        }

        if ($writeIfChanged && $this->owner->isChanged('LegalState', DataObject::CHANGE_VALUE)) {
            $this->owner->write();
        }
    }

    public function getBinaryLegalState()
    {
        switch ($this->owner->LegalState) {
            case self::STATE_INVALID:
                return '0';
            case self::STATE_VALID:
                return '1';
            case self::STATE_NONE:
                return '';
        }
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if (LegalFile::config()->do_onbefore_write) {
            $this->refreshLegalState();
        }
    }

    public function updateCMSFields(FieldList $fields)
    {
        if (!LegalFile::config()->update_cms_fields) {
            return;
        }

        $tabName = LegalFile::config()->tab_name;

        /** @var GridField $legalFiles */
        $legalFiles = $fields->dataFieldByName('LegalFiles');

        $LegalState = $fields->dataFieldByName("LegalState");
        $LegalStateChanged = $fields->dataFieldByName("LegalStateChanged");
        if (!$this->owner->ID) {
            $fields->removeByName('LegalState');
            $fields->removeByName('LegalStateChanged');
        } else {
            $insertBefore = $legalFiles ? "LegalFiles" : null;
            if ($LegalState) {
                $fields->addFieldToTab("Root." . $tabName, $LegalState, $insertBefore);
            }
            if ($LegalStateChanged) {
                $fields->addFieldToTab("Root." . $tabName, $LegalStateChanged, $insertBefore);
            }
        }
        if ($legalFiles) {
            $config = $legalFiles->getConfig();

            // Update summary fields

            if ($this->owner instanceof Member) {
                /** @var GridFieldDataColumns $dc */
                $dc = $config->getComponentByType(GridFieldDataColumns::class);
                if ($dc) {
                    $displayFields = $dc->getDisplayFields($legalFiles);
                    unset($displayFields['Member.FirstName']);
                    unset($displayFields['Member.Surname']);
                    $dc->setDisplayFields($displayFields);
                }
            }

            // No link existing
            $config->removeComponentsByType(GridFieldAddExistingAutocompleter::class);

            // No unlink
            $config->removeComponentsByType(GridFieldDeleteAction::class);
            $config->addComponent(new GridFieldDeleteAction(false));

            // Assign

            /** @var GridFieldDetailForm $detailForm */
            $detailForm = $config->getComponentByType(GridFieldDetailForm::class);
            $owner = $this->owner;
            if ($detailForm) {
                $detailForm->setItemEditFormCallback(function (Form $form) use ($owner) {
                    $parts = explode('\\', get_class($owner));
                    $class = end($parts);

                    // This assume that relation = class without namespace
                    $fieldName = $class . 'ID';
                    $form->Fields()->push(new HiddenField($fieldName, null, $owner->ID));
                });
            }
        }
    }
}
