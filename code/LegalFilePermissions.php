<?php

use SilverStripe\Security\Permission;

/**
 *
 */
trait LegalFilePermissions
{
    public function canView($member = null)
    {
        return Permission::check('CMS_ACCESS_LegalFilesAdmin', 'any', $member);
    }

    public function canEdit($member = null)
    {
        return Permission::check('CMS_ACCESS_LegalFilesAdmin', 'any', $member);
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::check('CMS_ACCESS_LegalFilesAdmin', 'any', $member);
    }

    public function canDelete($member = null)
    {
        return Permission::check('CMS_ACCESS_LegalFilesAdmin', 'any', $member);
    }
}
