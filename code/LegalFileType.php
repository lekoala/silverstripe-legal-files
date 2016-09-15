<?php

/**
 * Describe a type of legal file
 *
 * @author Koala
 * @property string $Title
 * @property string $Description
 * @property string $ApplyOnlyTo
 * @property boolean $CannotExpire
 * @property boolean $Mandatory
 * @method DataList|LegalFile[] Files()
 */
class LegalFileType extends DataObject
{
    private static $db             = array(
        'Title' => "Varchar(255)",
        'Description' => "Varchar(255)",
        'ApplyOnlyTo' => "Varchar(255)",
        'CannotExpire' => 'Boolean',
        'Mandatory' => 'Boolean',
    );
    private static $has_many       = array(
        'Files' => 'LegalFile',
    );
    private static $summary_fields = array(
        'Title',
    );
    private static $field_labels   = array(
        'CannotExpire' => "Cannot expire",
        'ApplyOnlyTo' => "Apply only to",
    );
    private static $default_sort   = array(
        'Title ASC'
    );

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $arr = LegalFilesExtension::listClassesWithLegalFile();
        if (count($arr) > 1) {
            $fields->replaceField('ApplyOnlyTo',
                $ApplyOnlyTo = new ListboxField('ApplyOnlyTo',
                $this->fieldLabel('ApplyOnlyTo'), ArrayLib::valuekey($arr)));
            $ApplyOnlyTo->setMultiple(true);
        } else {
            $fields->removeByName('ApplyOnlyTo');
        }

        return $fields;
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

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
    }

    protected function validate()
    {
        $result = parent::validate();
        if (!$this->Title) {
            $result->error("Title must be defined");
        }
        return $result;
    }
}