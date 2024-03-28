<?php

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Forms\ListboxField;
use SilverStripe\Assets\FileNameFilter;

/**
 * Describe a type of legal file
 *
 * @property string $Title
 * @property string $Description
 * @property string $ApplyOnlyTo
 * @property bool $CannotExpire
 * @property bool $Mandatory
 * @method \SilverStripe\ORM\DataList|\LegalFile[] Files()
 */
class LegalFileType extends DataObject
{
    use LegalFilePermissions;

    private static $db = array(
        'Title' => "Varchar(255)",
        'Description' => "Varchar(255)",
        'ApplyOnlyTo' => "Varchar(255)",
        'CannotExpire' => 'Boolean',
        'Mandatory' => 'Boolean',
    );
    private static $has_many = array(
        'Files' => 'LegalFile',
    );
    private static $summary_fields = array(
        'Title', 'Description'
    );
    private static $field_labels = array(
        'CannotExpire' => "Cannot expire",
        'ApplyOnlyTo' => "Applies only to",
    );
    private static $default_sort = array(
        'Title ASC'
    );
    private static $default_records = [
        [
            'Title' => 'National ID Card',
            'ApplyOnlyTo' => 'Member',
        ],
        [
            'Title' => 'Passport',
            'ApplyOnlyTo' => 'Member',
        ],
        [
            'Title' => 'Proof of Address',
            'ApplyOnlyTo' => 'Member',
        ],
        [
            'Title' => 'Proof of IBAN',
            'ApplyOnlyTo' => 'Member',
        ],
        [
            'Title' => 'Residence Permit',
            'ApplyOnlyTo' => 'Member',
        ],
        [
            'Title' => 'Company Registration',
            'CannotExpire' => true,
            'ApplyOnlyTo' => 'Company',
        ],
        [
            'Title' => 'Founding Document',
            'CannotExpire' => true,
            'ApplyOnlyTo' => 'Company',
        ],
    ];

    public function getName()
    {
        $filter = new FileNameFilter;
        return $filter->filter($this->Title);
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $arr = LegalFilesExtension::listClassesWithLegalFile();
        if (count($arr) > 1) {
            $labels = [];
            foreach ($arr as $class) {
                /* @var $singl DataObject */
                $singl = singleton($class);
                $labels[$class] = $singl->i18n_singular_name();
            }
            $fields->replaceField('ApplyOnlyTo', $ApplyOnlyTo = new ListboxField('ApplyOnlyTo', $this->fieldLabel('ApplyOnlyTo'), $labels));
        } else {
            $fields->removeByName('ApplyOnlyTo');
        }

        return $fields;
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
    }

    public function validate()
    {
        $result = parent::validate();
        if (!$this->Title) {
            $result->addError("Title must be defined");
        }
        return $result;
    }
}
