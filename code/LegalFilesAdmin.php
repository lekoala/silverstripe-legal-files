<?php

/**
 * Manage legal documents
 *
 * @author Koala
 */
class LegalFileAdmin extends ModelAdmin
{
    private static $managed_models = array(
        'LegalFile', 'LegalFileType'
    );
    private static $url_segment    = 'legal-documents';
    private static $menu_title     = 'Legal Documents';
    private static $awesome_icon   = "fa-file-text";
    public $showImportForm         = false;

    public function init()
    {
        parent::init();
        self::initLegalFilesFolder();
    }

    public function getList()
    {
        $list = parent::getList();

        return $list;
    }

    public function getSearchContext()
    {
        $context = parent::getSearchContext();
        $fields  = $context->getFields();

        $singl = singleton($this->modelClass);

        return $context;
    }

    public static function buildMemberEditLink(LegalFile $f)
    {
        return '/admin/legal-documents/LegalFile/EditForm/field/LegalFile/item/'.$f->ID.'/ItemEditForm/field/MemberID/item/'.$f->MemberID.'/view';
    }

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        $singl = singleton($this->modelClass);

        /* @var $gridfield GridField */
        $gridfield = $form->Fields()->dataFieldByName($this->modelClass);
        $config    = $gridfield->getConfig();

        if ($this->modelClass == 'LegalFile') {
            /* @var $cols GridFieldDataColumns */
            $cols = $gridfield->getConfig()->getComponentByType('GridFieldDataColumns');

            $cols->setFieldFormatting(array(
                'Member.Surname' => function($val, $item) {
                    if (!$val) {
                        return;
                    }
                    return '<a href="'.LegalFileAdmin::buildMemberEditLink($item).'">'.$val.'</a>';
                },
                'Member.FirstName' => function($val, $item) {
                    if (!$val) {
                        return;
                    }
                    return '<a href="'.LegalFileAdmin::buildMemberEditLink($item).'">'.$val.'</a>';
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
        return ASSETS_PATH.'/'.self::getLegalFilesDir();
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

    /**
     * Init dir
     */
    public static function initLegalFilesFolder()
    {
        $folder = self::getBaseLegalFilesFolder();
        $path   = $folder->getFullPath();

        // Restrict access to the storage folder
        if (!is_file($path.DIRECTORY_SEPARATOR.'.htaccess')) {
            $ressourcesPath = Director::baseFolder().DIRECTORY_SEPARATOR.LEGALFILES_DIR.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR;

            copy($ressourcesPath.'.htaccess',
                $path.DIRECTORY_SEPARATOR.'.htaccess');
        }
    }
}