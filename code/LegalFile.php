<?php

/**
 * Store a legal file
 *
 * @author Koala
 * @property string $ExpirationDate
 * @property string $Status
 * @property string $Notes
 * @property string $Reviewed
 * @property string $Reminded
 * @property string $Deleted
 * @property int $TypeID
 * @property int $FileID
 * @property int $MemberID
 * @property int $CompanyID
 * @property int $DeletedByID
 * @method LegalFileType Type()
 * @method File File()
 * @method Member Member()
 * @method Company Company()
 * @method Member DeletedBy()
 * @mixin MyLegalFile
 * @mixin SoftDeletable
 */
class LegalFile extends DataObject
{

    const STATUS_VALID = 'Valid';
    const STATUS_INVALID = 'Invalid';
    const STATUS_WAITING = 'Waiting';

    private static $db = array(
        'ExpirationDate' => 'Date',
        'Status' => "Enum('Waiting,Valid,Invalid','Waiting')",
        'Notes' => 'Text',
        'Reviewed' => 'SS_Datetime',
        'Reminded' => 'SS_Datetime',
    );
    private static $has_one = array(
        'Type' => 'LegalFileType',
        'File' => 'File',
        'Member' => 'Member',
    );
    private static $summary_fields = array(
        'Member.Surname' => 'Surname',
        'Member.FirstName' => 'First Name',
        'Type.Title' => 'Document Type',
        'ExpiresIn' => 'Expires in',
        'Reminded' => 'Reminded'
    );
    private static $default_sort = array(
        'ExpirationDate ASC'
    );
    private static $better_buttons_actions = array(
        'doValid', 'doInvalid'
    );

    public function doValid()
    {
        $this->Status = self::STATUS_VALID;
        $this->write();

        return _t('LegalFile.MARKED_VALID', 'Marked as valid');
    }

    public function doInvalid()
    {
        $this->Status = self::STATUS_INVALID;
        $this->write();

        return _t('LegalFile.MARKED_INVALID', 'Marked as invalid');
    }

    public function getBetterButtonsActions()
    {
        $fields = parent::getBetterButtonsActions();

        if (self::config()->validation_workflow) {
            if ($this->Status != self::STATUS_INVALID) {
                $fields->push(new BetterButtonCustomAction('doInvalid', _t('LegalFile.MARK_INVALID', 'Is invalid')));
            }
            if ($this->Status != self::STATUS_VALID) {
                $fields->push(new BetterButtonCustomAction('doInvalid', _t('LegalFile.MARK_VALID', 'Is valid')));
            }
        }

        return $fields;
    }

    public function summaryFields()
    {
        $fields = parent::summaryFields();

        if (self::config()->validation_workflow) {
            $fields['TranslatedStatus'] = _t('LegalFile.SUMMARY_STATUS', 'Status');
        }

        return $fields;
    }

    /**
     * SilverStripe message class
     *
     * @return string
     */
    public function SilverStripeClass()
    {
        switch ($this->Status) {
            case self::STATUS_VALID:
                return 'good';
            case self::STATUS_INVALID:
                return 'bad';
            case self::STATUS_WAITING:
                return 'info';
        }
    }

    /**
     * A user formatted date
     * @return string
     */
    public function getFormattedDate()
    {
        $date = new Date();
        $date->setValue($this->LastEdited);
        return Convert::raw2xml($date->FormatFromSettings());
    }

    /**
     * A line describing the status of this file
     *
     * @return string
     */
    public function FullStatus()
    {
        return _t('LegalFile.FULL_STATUS', 'This document was submitted on {date} and is {status}', [
            'date' => $this->getFormattedDate(),
            'status' => $this->TranslatedStatus(),
        ]);
    }

    /**
     * The translated status
     *
     * @return string
     */
    public function TranslatedStatus()
    {
        switch ($this->Status) {
            case self::STATUS_VALID:
                return _t('LegalFile.STATUS_VALID', 'valid');
            case self::STATUS_INVALID:
                return _t('LegalFile.STATUS_INVALID', 'invalid');
            case self::STATUS_WAITING:
                return _t('LegalFile.STATUS_WAITING', 'waiting');
        }
    }

    public function canView($member = null)
    {
        return Permission::check('CMS_ACCESS_LegalFilesAdmin', 'any', $member);
    }

    public function canEdit($member = null)
    {
        return Permission::check('CMS_ACCESS_LegalFilesAdmin', 'any', $member);
    }

    public function canCreate($member = null)
    {
        return Permission::check('CMS_ACCESS_LegalFilesAdmin', 'any', $member);
    }

    public function canDelete($member = null)
    {
        return Permission::check('CMS_ACCESS_LegalFilesAdmin', 'any', $member);
    }

    public function getTitle()
    {
        if (!$this->TypeID) {
            return _t('LegalFile.NEW_LEGAL_DOCUMENT', 'New legal document');
        }
        $owner = $this->OwnerObject();

        $type = $this->Type()->getTitle();
        if ($owner) {
            $owner = $owner->getTitle();
        } else {
            $owner = _t('LegalFile.UNDEFINED_OWNER', 'Undefined owner');
        }

        return $type . ' ' . _t('LegalFile.FOR', 'for') . ' ' . $owner;
    }

    public function getRowClass()
    {
        $stat = $this->Status;

        if (!$this->ExpirationDate) {
            if ($stat == 'Invalid') {
                return 'red';
            }
            if ($stat == 'Valid') {
                return 'green';
            }
            return '';
        }
        $dt = new DateTime($this->ExpirationDate);
        $dt2 = new DateTime();
        $diff = date_diff($dt, $dt2);
        $diff_days = $diff->format("%a");
        $days = self::config()->days_before_reminder;

        // We have a negative value, it's not valid!
        if (!$diff->invert) {
            return 'red';
        }

        if ($stat == 'Invalid') {
            return 'red';
        } else {
            // Warn about documents that are about to expire
            if ($days && $diff_days < $days) {
                return 'amber';
            }
        }
        if ($stat == 'Waiting') {
            return 'amber';
        }
        if ($stat == 'Valid') {
            return 'green';
        }
        return '';
    }

    public function IsExpired()
    {
        if (!$this->ExpirationDate) {
            return false;
        }
        $dt = new DateTime($this->ExpirationDate);
        $dt2 = new DateTime();
        $diff = date_diff($dt, $dt2);
        if (!$diff->invert) {
            return true;
        }
        return false;
    }

    public function ExpiresIn()
    {
        if (!$this->ExpirationDate) {
            return 'No expiration date';
        }
        $dt = new DateTime($this->ExpirationDate);
        $dt2 = new DateTime();
        $diff = date_diff($dt, $dt2);
        if (!$diff->invert) {
            return 'Expired since ' . $diff->format("%a") . ' days';
        }
        return 'Expires in ' . $diff->format("%a") . ' days';
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if ($this->isChanged('Status', 2)) {
            $this->Reviewed = date('Y-m-d H:i:s');
        }
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();

        if ($this->FileID) {
            $f = $this->File();
            $ext = $f->getExtension();
            $f->setName('Doc' . $this->ID . '.' . $ext);
            $f->write();
        }
    }

    protected function validate()
    {
        $result = parent::validate();
        if (!$this->TypeID) {
            $result->error("Type must be defined");
        }
        if (!$this->OwnerClass()) {
            $result->error("Must have a owner");
        }
        return $result;
    }

    /**
     * Return an array of types
     *
     * @param string $forClass
     * @return array
     */
    public static function listTypes($forClass = null)
    {
        return self::TypesDatalist($forClass)->map()->toArray();
    }

    /**
     * Return a list of types
     *
     * @param string $forClass
     * @return DataList|LegalFileType[]
     */
    public static function TypesDatalist($forClass = null)
    {
        $q = LegalFileType::get();
        if ($forClass) {
            //TODO: like clause may fail
            $q = $q->where("ApplyOnlyTo IS NULL OR ApplyOnlyTo LIKE '%$forClass%'");
        }
        return $q;
    }

    /**
     * Look for a owner. This suppose we have only ONE owner and that
     * the owner relation name matches the name of the class
     *
     * @return string
     */
    public function OwnerClass()
    {
        $classes = LegalFilesExtension::listClassesWithLegalFile();
        $has_one = self::config()->has_one;
        foreach ($has_one as $rel => $cl) {
            if (!in_array($cl, $classes)) {
                continue;
            }
            $f = $rel . 'ID';
            if ($this->$f) {
                return $cl;
            }
        }
    }

    /**
     * Return owner as a DataObject
     *
     * @return DataObject
     */
    public function OwnerObject()
    {
        $classes = LegalFilesExtension::listClassesWithLegalFile();
        $has_one = self::config()->has_one;
        foreach ($has_one as $rel => $cl) {
            if (!in_array($cl, $classes)) {
                continue;
            }
            $f = $rel . 'ID';
            if ($this->$f) {
                return $this->$rel();
            }
        }
    }

    /**
     * @return int
     */
    public function OwnerID()
    {
        $classes = LegalFilesExtension::listClassesWithLegalFile();
        $has_one = self::config()->has_one;
        foreach ($has_one as $rel => $cl) {
            if (!in_array($cl, $classes)) {
                continue;
            }
            $f = $rel . 'ID';
            if ($this->$f) {
                return $this->$f;
            }
        }
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $ownerClass = $this->OwnerClass();

        // Validation workflow
        if (!self::config()->validation_workflow) {
            $fields->removeByName('Status');
            $fields->removeByName('Notes');
            $fields->removeByName('Reviewed');
        } else {
            // Reviewed
            if (!$this->FileID) {
                $fields->removeByName('Reviewed');
            } else {
                $fields->makeFieldReadonly('Reviewed');
            }
        }

        if (self::config()->enable_storage) {
            /* @var $File UploadField */
            $File = $fields->dataFieldByName('File');
            $File->setCanAttachExisting(false);
            $File->setFolderName(self::config()->upload_folder);
            $File->setTemplateFileButtons('LegalUploadField_FileButtons');

            // Preview frame
            if ($this->FileID) {
                $file = $this->File();

                // Only show if previewable
                if (in_array($file->getExtension(), array('jpg', 'png', 'pdf'))) {
                    $previewLink = $file->Link() . '?inline=true';

                    $iframe = new LiteralField('iframe', '<iframe src="' . $previewLink . '" style="width:100%;background:#fff;min-height:100%;min-height:500px;vertical-align:top"></iframe>');

                    $fields->addFieldToTab('Root.Preview', $iframe);
                }

                // Downloadable button
                $fields->insertAfter(new LiteralField('download_link', '<a class="ss-ui-button" href="' . $file->Link() . '">' . _t('LegalFile.DOWNLOAD_FILE', 'Download file') . '</a>'), 'File');
            }
        } else {
            $fields->removeByName('File');
            $fields->removeByName('FileID');
        }


        $fields->makeFieldReadonly('Reminded');

        // Only display valid types for given class
        $types = self::listTypes($ownerClass);
        $fields->removeByName('TypeID');
        if (!empty($types)) {
            $fields->insertBefore(
                new DropdownField('TypeID', $this->fieldLabel('Type'), $types), 'ExpirationDate');
        }

        // If we have a type, it might change some fields
        if ($this->TypeID) {
            if ($this->Type()->CannotExpire) {
                $fields->removeByName('ExpirationDate');
            }
        }

        // Filter fields that are not needed, we can only attach to one record
        $classes = LegalFilesExtension::listClassesWithLegalFile();
        foreach ($classes as $class) {
            if ($ownerClass && $class != $ownerClass) {
                $fields->removeByName($class . 'ID');
                continue;
            }

            $newField = null;
            $fieldName = $class . 'ID';

            if ($ownerClass) {
                $gfc = GridFieldConfig_RecordViewer::create();
                $gfc->removeComponentsByType('GridFieldSortableHeader');
                $gfc->removeComponentsByType('GridFieldFilterHeader');
                $gfc->removeComponentsByType('GridFieldPaginator');
                $gfc->removeComponentsByType('GridFieldPageCount');
                $gfc->removeComponentsByType('GridFieldToolbarHeader');

                $newField = new GridField($fieldName, '', $class::get()->filter('ID', $this->OwnerID()), $gfc);
                $newField->setModelClass($class);
            } else {
                if (class_exists('HasOnePickerField')) {
                    $newField = new HasOnePickerField($this, $fieldName, '', $this->$class());
                    $gfc = $newField->getConfig();
                    $gfc->removeComponentsByType('GridFieldToolbarHeader');
                }
            }

            if ($newField) {
                $fields->addFieldToTab('Root.Main', $newField);
            }
        }

        return $fields;
    }
}
