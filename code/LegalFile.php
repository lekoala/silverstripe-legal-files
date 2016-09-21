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
    const STATUS_VALID   = 'Valid';
    const STATUS_INVALID = 'Invalid';
    const STATUS_WAITING = 'Waiting';

    private static $db             = array(
        'ExpirationDate' => 'Date',
        'Status' => "Enum('Waiting,Valid,Invalid','Waiting')",
        'Notes' => 'Text',
        'Reviewed' => 'SS_Datetime',
        'Reminded' => 'SS_Datetime',
    );
    private static $has_one        = array(
        'Type' => 'LegalFileType',
        'File' => 'File',
        'Member' => 'Member',
    );
    private static $summary_fields = array(
        'Type.Title' => 'Document Type',
        'ExpirationDate' => 'Expiration Date',
        'ExpiresIn' => 'Expires in',
        'Reminded' => 'Reminded'
    );
    private static $default_sort   = array(
        'ExpirationDate ASC'
    );

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
        $dt        = new DateTime($this->ExpirationDate);
        $dt2       = new DateTime();
        $diff      = date_diff($dt, $dt2);
        $diff_days = $diff->format("%a");
        $days      = self::config()->days_before_reminder;

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

    public function ExpiresIn()
    {
        if (!$this->ExpirationDate) {
            return 'No expiration date';
        }
        $dt   = new DateTime($this->ExpirationDate);
        $dt2  = new DateTime();
        $diff = date_diff($dt, $dt2);
        if (!$diff->invert) {
            return 'Expired since '.$diff->format("%d").' days';
        }
        return 'Expires in '.$diff->format("%d").' days';
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();

        if ($this->FileID) {
            $f   = $this->File();
            $ext = $f->getExtension();
            $f->setName('Doc'.$this->ID.'.'.$ext);
            $f->write();
        }
    }

    protected function validate()
    {
        $result = parent::validate();
        if (!$this->TypeID) {
            $result->error("Type must be defined");
        }
        if(!$this->OwnerClass()) {
            $result->error("Must have a owner");
        }
        return $result;
    }

    public static function listTypes($forClass = null)
    {
        $q = LegalFileType::get();
        if ($forClass) {
            //TODO: like clause may fail
            $q = $q->where("ApplyOnlyTo IS NULL OR ApplyOnlyTo LIKE '%$forClass%'");
        }
        return $q->map()->toArray();
    }

    public function OwnerClass()
    {
        $classes = LegalFilesExtension::listClassesWithLegalFile();
        $has_one = self::config()->has_one;
        foreach ($has_one as $rel => $cl) {
            if (!in_array($cl, $classes)) {
                continue;
            }
            $f = $rel.'ID';
            if ($this->$f) {
                return $cl;
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
            // Set upload path
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
                    $previewLink = $file->Link().'?inline=true';

                    $iframe = new LiteralField('iframe',
                        '<iframe src="'.$previewLink.'" style="width:100%;background:#fff;min-height:100%;min-height:500px;vertical-align:top"></iframe>');

                    $fields->addFieldToTab('Root.Preview', $iframe);
                }

                // Downloadable button
                $fields->insertAfter(new LiteralField('download_link',
                    '<a class="ss-ui-button" href="'.$file->Link().'">'._t('LegalFile.DOWNLOAD_FILE',
                        'Download file').'</a>'), 'File');
            }
        } else {
            $fields->removeByName('File');
            $fields->removeByName('FileID');
        }


        $fields->makeFieldReadonly('Reminded');

        // Only display valid types
        $types = self::listTypes($ownerClass);
        $fields->removeByName('TypeID');
        if (!empty($types)) {
            $fields->insertBefore(
                new DropdownField('TypeID', $this->fieldLabel('Type'), $types),
                'ExpirationDate');
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
            if ($class != $ownerClass) {
                $fields->removeByName($class.'ID');
            } else {
                if (class_exists('HasOnePickerField')) {
                    $fields->replaceField($class.'ID',
                        $picker = new HasOnePickerField($this, $class . 'ID',
                        $this->fieldLabel($class), $this->$class()));

                    $picker->getConfig()->removeComponentsByType('PickerFieldDeleteAction');
                    $picker->enableEdit();
                } else {
                    $fields->makeFieldReadonly($class.'ID');
                }
            }
        }

        return $fields;
    }
}