<?php

use SilverStripe\Assets\Folder;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridFieldDataColumns;

/**
 * Manage legal documents
 *
 * @author Koala
 */
class LegalFilesAdmin extends ModelAdmin
{
    private static $managed_models = array(
        'LegalFile', 'LegalFileType'
    );
    private static $url_segment = 'legal-documents';
    private static $menu_title = 'Legal Documents';
    private static $menu_icon_class = 'font-icon-book';
    public $showImportForm = false;

    public function init()
    {
        parent::init();
    }

    public function getList()
    {
        $list = parent::getList();

        return $list;
    }

    public function getSearchContext()
    {
        $context = parent::getSearchContext();
        $fields = $context->getFields();

        $singl = singleton($this->modelClass);

        return $context;
    }

    public static function buildMemberEditLink(LegalFile $f)
    {
        return '/admin/legal-documents/LegalFile/EditForm/field/LegalFile/item/' . $f->ID . '/ItemEditForm/field/MemberID/item/' . $f->MemberID . '/view';
    }

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        $singl = singleton($this->modelClass);

        /* @var $gridfield GridField */
        $gridfield = $form->Fields()->dataFieldByName($this->modelClass);
        $config = $gridfield->getConfig();

        if ($this->modelClass == 'LegalFile') {
            /* @var $cols GridFieldDataColumns */
            $cols = $gridfield->getConfig()->getComponentByType(GridFieldDataColumns::class);
            $cols->setFieldFormatting(array(
                'Member.Surname' => function ($val, $item) {
                    if (!$val) {
                        return;
                    }
                    return '<a href="' . LegalFilesAdmin::buildMemberEditLink($item) . '">' . $val . '</a>';
                },
                'Member.FirstName' => function ($val, $item) {
                    if (!$val) {
                        return;
                    }
                    return '<a href="' . LegalFilesAdmin::buildMemberEditLink($item) . '">' . $val . '</a>';
                },
            ));
        }
        return $form;
    }

    /**
     * Base path in asset folder
     *
     * @return string
     */
    public static function getLegalFilesPath()
    {
        return ASSETS_PATH . '/' . self::getLegalFilesDir();
    }

    /**
     * The base folder
     *
     * @return Folder
     */
    public static function getBaseLegalFilesFolder()
    {
        return Folder::find_or_make(self::getLegalFilesDir());
    }

    /**
     * Base directory in config.yml
     *
     * @return string
     */
    public static function getLegalFilesDir()
    {
        return LegalFile::config()->upload_folder;
    }
}
