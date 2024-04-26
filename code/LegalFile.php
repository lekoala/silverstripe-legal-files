<?php

use SilverStripe\Assets\File;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\DateField;
use SilverStripe\Security\Member;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldPageCount;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use Symbiote\GridFieldExtensions\GridFieldTitleHeader;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;

/**
 * Store a legal file
 *
 * @property ?string $ExpirationDate
 * @property ?string $Status
 * @property ?string $Notes
 * @property ?string $Reviewed
 * @property ?string $Reminded
 * @property int $ReviewMemberID
 * @property int $CompanyID
 * @property int $MemberCompanyID
 * @property int $TypeID
 * @property int $FileID
 * @property int $MemberID
 * @method \Company Company()
 * @method \Company MemberCompany()
 * @method \LegalFileType Type()
 * @method \SilverStripe\Assets\File File()
 * @method \SilverStripe\Security\Member Member()
 * @mixin \MyLegalFile
 * @mixin \LeKoala\Base\Extensions\BaseDataObjectExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class LegalFile extends DataObject
{
    use LegalFilePermissions;

    // Status
    const STATUS_VALID = 'Valid';
    const STATUS_INVALID = 'Invalid';
    const STATUS_WAITING = 'Waiting';

    private static $db = [
        'ExpirationDate' => 'Date',
        'Status' => "Enum('Waiting,Valid,Invalid','Waiting')",
        'Notes' => 'Text',
        'Reviewed' => 'Datetime',
        'Reminded' => 'Datetime',
        // we don't use has one relation to avoid multiple members relation
        'ReviewMemberID' => 'Int',
    ];
    private static $has_one = [
        'Type' => 'LegalFileType',
        'File' => File::class,
        'Member' => Member::class,
    ];
    private static $summary_fields = [
        'Member.Surname' => 'Surname',
        'Member.FirstName' => 'First Name',
        'Created' => 'Uploaded',
        'Reminded' => 'Reminded',
    ];
    private static $owns = [
        "File"
    ];
    private static $cascade_delete = [
        "File"
    ];
    /**
     * @link https://docs.silverstripe.org/en/4/developer_guides/model/scaffolding/
     * @var array
     */
    private static $searchable_fields = [
        'Member.Surname',
        'Member.FirstName',
        'Created',
        'Reminded',
    ];
    private static $default_sort = [
        'ExpirationDate ASC'
    ];

    public static function listValidExtensions()
    {
        return self::config()->valid_extensions;
    }

    public function doValid()
    {
        $this->Status = self::STATUS_VALID;
        $this->write();

        $template = 'LegalFilesDocumentValidEmail';
        $emailTitle = _t('LegalFilesDocumentValidEmail.SUBJECT', "A legal document has been marked has valid");
        $email = LegalFileEmail::getEmail($this, $emailTitle, $template);
        if ($email->To()) {
            $email->send();
        }

        return _t('LegalFile.MARKED_VALID', 'Marked as valid');
    }

    public function doInvalid()
    {
        $this->Status = self::STATUS_INVALID;
        $this->write();

        $template = 'LegalFilesDocumentInvalidEmail';
        $emailTitle = _t('LegalFilesDocumentInvalidEmail.SUBJECT', "A legal document has been marked has invalid");
        $email = LegalFileEmail::getEmail($this, $emailTitle, $template);
        if ($email->getTo()) {
            $email->send();
        }

        return _t('LegalFile.MARKED_INVALID', 'Marked as invalid');
    }

    public function doWaiting()
    {
        $this->Status = self::STATUS_WAITING;
        $this->write();

        return _t('LegalFile.STATUS_WAITING', 'Marked as waiting');
    }

    public function summaryFields()
    {
        $fields = parent::summaryFields();

        if (self::config()->validation_workflow) {
            $fields['TranslatedStatus'] = _t('LegalFile.SUMMARY_STATUS', 'Status');
        }

        return $fields;
    }

    public function searchableFields()
    {
        $fields = parent::searchableFields();

        if (self::config()->validation_workflow) {
            $fields['Status'] = [
                'title' => _t('LegalFile.SUMMARY_STATUS', 'Status'),
                'filter' => 'ExactMatchFilter',
            ];
        }

        $fields['Created']['field'] = DateField::class;
        $fields['Reminded']['field'] = DateField::class;

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
        $date = new DBDate();
        $date->setValue($this->LastEdited);
        return Convert::raw2xml($date->Nice());
    }

    /**
     * A line describing the status of this file
     *
     * @return string
     */
    public function FullStatus()
    {
        return _t('LegalFile.FULL_STATUS', '{type}: submitted on {date} and is {status}', [
            'type' => $this->Type()->Title,
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

        if (self::config()->validation_workflow) {
            if ($stat == 'Invalid') {
                return 'red';
            }
            if ($stat == 'Waiting') {
                return 'amber';
            }
            if ($stat == 'Valid') {
                return 'green';
            }
            return '';
        }

        if (!$this->ExpirationDate) {
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

        // Warn about documents that are about to expire
        if ($days && $diff_days < $days) {
            return 'amber';
        }
        if ($days && $diff_days > $days) {
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

    public function IsValid()
    {
        return $this->Status == self::STATUS_VALID || !$this->IsExpired();
    }

    public function IsInvalid()
    {
        return $this->Status == self::STATUS_INVALID || $this->IsExpired();
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
            $this->ReviewMemberID = Member::currentUserID();
        }
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();

        if ($this->FileID && $this->File()->exists()) {
            /* @var $f File */
            $f = $this->File();
            $ext = $f->getExtension();
            $newName = 'Doc' . $this->ID . '.' . $ext;
            if ($newName != $f->getField('Name')) {
                $f->Name = $newName;
                $f->write();
            }
        }
    }

    public function validate()
    {
        $result = parent::validate();
        if (!$this->TypeID) {
            $result->addError("Type must be defined");
        }
        if (!$this->OwnerClass()) {
            $result->addError("Must have a owner");
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
     * Get maximum file size in bytes
     *
     * @return int
     */
    public static function getMaxSize()
    {
        $maxUpload = File::ini2bytes(ini_get('upload_max_filesize'));
        $maxPost = File::ini2bytes(ini_get('post_max_size'));
        $legalSize = File::ini2bytes(LegalFile::config()->max_size);

        return min($maxPost, $maxUpload, $legalSize);
    }

    /**
     * Return a list of types
     *
     * @param string $forClass
     * @return DataList|LegalFileType[]
     */
    public static function TypesDatalist($forClass = null)
    {
        if ($forClass === null) {
            return LegalFileType::get()->filter('ID', 0);
        }
        $parts = explode('\\', $forClass);
        $forClass = end($parts);
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
        $object = $this->OwnerObject();
        if ($object) {
            return get_class($object);
        }
        return null;
    }

    /**
     * Return owner as a DataObject
     *
     * @param bool $onlyID
     * @return DataObject
     */
    public function OwnerObject($onlyID = false)
    {
        $classes = LegalFilesExtension::listClassesWithLegalFile();
        $has_one = self::config()->has_one;
        $ignoredRelations = [];
        if ($this->hasMethod('getIgnoredLegalFilesRelations')) {
            $ignoredRelations = $this->getIgnoredLegalFilesRelations();
        }
        foreach ($has_one as $rel => $cl) {
            if (!in_array($cl, $classes)) {
                continue;
            }
            if (in_array($rel, $ignoredRelations)) {
                continue;
            }
            $f = $rel . 'ID';
            if ($this->$f) {
                if ($onlyID) {
                    return $this->$f;
                }
                return $this->$rel();
            }
        }
    }

    /**
     * @return int
     */
    public function OwnerID()
    {
        return $this->OwnerObject(true);
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $ownerClass = $this->OwnerClass();

        $fields->removeByName('ReviewMemberID');

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
                $fields->replaceField('Reviewed', new ReadonlyField('ReviewedBy', $this->fieldLabel('Reviewed'), _t('LegalFile.REVIEWED_BY', "Reviewed at {date} by {member}", [
                    'date' => $this->Reviewed,
                    'member' => $this->ReviewMemberID ? $this->ReviewMember()->getTitle() : 'unknown'
                ])));
            }

            $fields->replaceField('Status', new ReadonlyField('TranslatedStatusText', $this->fieldLabel('Status'), $this->TranslatedStatus()));
        }

        if (self::config()->enable_storage) {
            $File = $this->getUploadField($fields);
            $File->setFolderName(self::config()->upload_folder);
            $File->getValidator()->setAllowedExtensions(self::listValidExtensions());
            $File->getValidator()->setAllowedMaxFileSize(self::getMaxSize());

            // Preview frame
            if ($this->FileID) {
                $file = $this->File();

                // Only show if previewable (images only, pdf does not always work)
                if (in_array($file->getExtension(), ['jpg', 'png'])) {
                    $previewLink = $this->Link(['inline' => true]);

                    $iframe = new LiteralField('iframe', '<iframe src="' . $previewLink . '" style="width:100%;background:#fff;min-height:100%;min-height:500px;vertical-align:top"></iframe>');

                    $fields->addFieldToTab('Root.Preview', $iframe);
                }

                // Downloadable button
                $fields->insertAfter(new LiteralField('download_link', '<a class="ss-ui-button" target="_blank" href="' . $this->Link() . '">' . _t('LegalFile.DOWNLOAD_FILE', 'Download file') . '</a>'), 'File');
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
                new DropdownField('TypeID', $this->fieldLabel('Type'), $types),
                'ExpirationDate'
            );
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
            $subClasses = ClassInfo::subclassesFor($class);
            if ($ownerClass && !in_array($ownerClass, array_values($subClasses))) {
                $fields->removeByName($class . 'ID');
                continue;
            }

            $parts = explode('\\', $class);
            $simpleClass = end($parts);

            $newField = null;
            $fieldName = $simpleClass . 'ID';

            // We have a current owner, display its details in a gridfield
            if ($ownerClass) {
                // $this->OwnerObject()->refreshLegalState(true);

                $gfc = GridFieldConfig_RecordEditor::create();
                $gfc->removeComponentsByType(GridFieldSortableHeader::class);
                $gfc->removeComponentsByType(GridFieldFilterHeader::class);
                $gfc->removeComponentsByType(GridFieldPaginator::class);
                $gfc->removeComponentsByType(GridFieldPageCount::class);
                $gfc->removeComponentsByType(GridFieldDeleteAction::class);
                $gfc->removeComponentsByType(GridFieldAddNewButton::class);
                $gfc->addComponent(new GridFieldTitleHeader());

                $newField = new GridField($fieldName, '', $class::get()->filter('ID', $this->OwnerID()), $gfc);
                $newField->setModelClass($class);

                $summaryFields = singleton($class)->summaryFields();
                $summaryFields['TranslatedLegalState'] = _t('LegalFile.LegalState', 'Legal State');
                /* @var $cols GridFieldDataColumns */
                $cols = $this->getGridFieldDataColumns($gfc);
                if ($cols) {
                    $cols->setDisplayFields($summaryFields);
                }
            } else {
                // We don't have a owner, show a picker field
                // if (class_exists('HasOnePickerField')) {
                //     $newField = new HasOnePickerField($this, $fieldName, '', $this->$class());
                //     $newField->enableEdit();
                //     $gfc = $newField->getConfig();
                // }
            }

            if ($newField) {
                $fields->addFieldToTab('Root.Main', $newField);
            }
        }

        return $fields;
    }

    /**
     * @param GridFieldConfig $gfc
     * @return GridFieldDataColumns
     */
    protected function getGridFieldDataColumns($gfc)
    {
        return $gfc->getComponentByType(GridFieldDataColumns::class);
    }

    public function Link($params = [])
    {
        $params = array_merge(['id' => $this->FileID, $params]);
        return Director::absoluteURL('_legalfiles/?' . http_build_query($params));
    }

    /**
     * IDE Helper
     *
     * @param FieldList $fields
     * @return UploadField
     */
    protected function getUploadField($fields)
    {
        $File = $fields->dataFieldByName('File');
        return $File;
    }
}
