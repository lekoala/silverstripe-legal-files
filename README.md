SilverStripe Legal Files module
==================

WARNING : This is very much work in progress!

Introduction
------------------

This module adds a new admin section to allow administration of legal files.

Attaching to other classes
------------------

Legal files always belong to a Member but can also be attached to any DataObject
through the LegalFileExtension (for example, for a Company).

Since LegalFiles are represented as a has_many relation, you need to define
a has_one relation for each class through a DataExtension, like so:

    class MyLegalFile extends DataExtension
    {
        private static $has_one = array(
            'Company' => "Company",
        );

        public function onBeforeWrite()
        {
            parent::onBeforeWrite();
            if (!$this->owner->MemberID && $this->owner->CompanyID) {
                $this->owner->MemberID = $this->owner->Company()->AdminID;
            }
        }
    }

Securing assets
------------------

The root folder will get an htaccess that will redirect all requests to a
dedicated controller. This will only work with Apache.
TODO : support private assets or secure assets module.

Remind members about expiring document
------------------

A Cron task allows you to remind members about expiring document. You can
overwrite the html template.

Adding colors to rows (3.4+)
------------------

Make sure you have the following GridField extension :

    class GridFieldColoredRow extends Extension
    {

        public function updateNewRowClasses(&$classes, $total, $index, $record)
        {
            if ($record->hasMethod('getRowClass')) {
                $class = $record->getRowClass($total, $index, $record);
                if ($class) {
                    $classes[] = $class;
                }
            }
        }
    }

And the following css :

    .cms table.ss-gridfield-table tr.green.odd { background-color : #DAF2DA; }
    .cms table.ss-gridfield-table tr.green.even { background-color: #C2F2C1; }
    .cms table.ss-gridfield-table tr.blue.odd { background-color: #D9EDF7; }
    .cms table.ss-gridfield-table tr.blue.even { background-color: #BCE8F1; }
    .cms table.ss-gridfield-table tr.amber.odd { background-color: #FAEBCC; }
    .cms table.ss-gridfield-table tr.amber.even { background-color: #FCF8E3; }
    .cms table.ss-gridfield-table tr.red.odd { background-color: #F2DEDE; }
    .cms table.ss-gridfield-table tr.red.even { background-color: #EBCCD1; }

Compatibility
==================
Tested with ^4.1

Maintainer
==================
LeKoala - thomas@lekoala.be
