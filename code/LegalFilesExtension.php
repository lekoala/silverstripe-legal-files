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
}