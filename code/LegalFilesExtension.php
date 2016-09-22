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

    public static function listClassesWithLegalFile()
    {
        if (self::$legalFileObjects === null) {
            self::$legalFileObjects = array();
            $dataobjects            = ClassInfo::subclassesFor('DataObject');
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

    public function updateCMSFields(\FieldList $fields)
    {
        /* @var $legalFiles GridField */
        $legalFiles = $fields->dataFieldByName('LegalFiles');
        if ($legalFiles) {
            $config = $legalFiles->getConfig();

            // No link existing
            $config->removeComponentsByType('GridFieldAddExistingAutocompleter');

            // No unlink
            $config->removeComponentsByType('GridFieldDeleteAction');
            $config->addComponent(new GridFieldDeleteAction(false));

            // Assign

            /* @var $detailForm GridFieldDetailForm */
            $detailForm = $config->getComponentByType('GridFieldDetailForm');
            $owner      = $this->owner;
            $base       = $this->ownerBaseClass;
            $detailForm->setItemEditFormCallback(function(Form $form) use ($owner, $base) {
                $fieldName = $base . 'ID';
                $form->Fields()->push(new HiddenField($fieldName,null,$owner->ID));
            });

            // Bulk manager
            if (class_exists('GridFieldBulkManager')) {
                $config->addComponent($bulkManager = new GridFieldBulkManager);
                $bulkManager->removeBulkAction('unLink');

                if (LegalFile::config()->enable_storage) {
                    $config->addComponent($bulkUpload = new GridFieldBulkUpload);

                    if ($this->owner->hasMethod('getOwnFolder')) {
                        $bulkUpload->setUfSetup('setFolderName',
                            $this->owner->getOwnFolder());
                    }
                    $bulkUpload->setUfSetup('setCanAttachExisting', false);
                }
            }
        }
    }
}