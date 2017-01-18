<?php

/**
 * Simply apply this extension to any type of record that use legal files
 *
 * @author Koala
 * @property Member|Company|LegalFilesExtension $owner
 * @method DataList|LegalFile[] LegalFiles()
 */
class LegalFilesExtension extends DataExtension
{

    private static $has_many = array(
        'LegalFiles' => 'LegalFile',
    );
    protected static $legalFileObjects;
    private static $better_buttons_actions = array(
        'doSendLegalFilesReminder'
    );

    public function updateBetterButtonsActions(FieldList $actions)
    {
        // If the owner is not created, no need to check anything
        if (!$this->owner->ID) {
            return;
        }
        $files = $this->getAboutToExpireLegalFiles()->where('Reminded IS NULL');
        if ($files->count()) {
            $actions->push(new BetterButtonCustomAction('doSendLegalFilesReminder', _t('LegalFile.SEND_REMINDER', 'Send legal documents reminder')));
        }
    }

    public static function listClassesWithLegalFile()
    {
        if (self::$legalFileObjects === null) {
            self::$legalFileObjects = array();
            $dataobjects = ClassInfo::subclassesFor('DataObject');
            foreach ($dataobjects as $dataobject) {
                $singl = singleton($dataobject);

                if ($singl->hasExtension('LegalFilesExtension')) {
                    // Ignore custom classes
                    $parent = get_parent_class($dataobject);
                    if ($parent != 'DataObject') {
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
            $class = $this->ownerBaseClass;
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

        move_uploaded_file($filename, $base . '/' . $relativeFilePath);

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
            $email->send();
        }

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

    public function updateCMSFields(\FieldList $fields)
    {
        /* @var $legalFiles GridField */
        $legalFiles = $fields->dataFieldByName('LegalFiles');
        if ($legalFiles) {
            $config = $legalFiles->getConfig();

            // Update summary fields

            /* @var $dc GridFieldDataColumns */
            if ($this->ownerBaseClass == 'Member') {
                $dc = $config->getComponentByType('GridFieldDataColumns');
                $displayFields = $dc->getDisplayFields($legalFiles);
                unset($displayFields['Member.FirstName']);
                unset($displayFields['Member.Surname']);
                $dc->setDisplayFields($displayFields);
            }

            // No link existing
            $config->removeComponentsByType('GridFieldAddExistingAutocompleter');

            // No unlink
            $config->removeComponentsByType('GridFieldDeleteAction');
            $config->addComponent(new GridFieldDeleteAction(false));

            // Assign

            /* @var $detailForm GridFieldDetailForm */
            $detailForm = $config->getComponentByType('GridFieldDetailForm');
            $owner = $this->owner;
            $base = $this->ownerBaseClass;
            $detailForm->setItemEditFormCallback(function(Form $form) use ($owner, $base) {
                $fieldName = $base . 'ID';
                $form->Fields()->push(new HiddenField($fieldName, null, $owner->ID));
            });

            // Bulk manager
            if (class_exists('GridFieldBulkManager')) {
                $config->addComponent($bulkManager = new GridFieldBulkManager);
                $bulkManager->removeBulkAction('unLink');

                if (LegalFile::config()->enable_storage) {
                    $config->addComponent($bulkUpload = new GridFieldBulkUpload);

                    if ($this->owner->hasMethod('getOwnFolder')) {
                        $bulkUpload->setUfSetup('setFolderName', $this->owner->getOwnFolder());
                    }
                    $bulkUpload->setUfSetup('setCanAttachExisting', false);
                }
            }
        }
    }
}
