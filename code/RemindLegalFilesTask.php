<?php

/**
 * A cron task to remind people about the expiration of their documents
 *
 * @author Koala
 */
class RemindLegalFilesTask implements CronTask
{

    /**
     * run this task every every day
     *
     * @return string
     */
    public function getSchedule()
    {
        return "0 3 * * *";
    }

    /**
     *
     * @return void
     */
    public function process()
    {
        $days = LegalFile::config()->days_before_reminder;
        if (!$days) {
            return 'No days before reminder have been set';
        }
        $files = LegalFile::get()
            ->filter('ExpirationDate:LessThan', date('Y-m-d', strtotime('+' . $days . ' days')))
            ->exclude('MemberID', 0)
            ->where('Reminded IS NULL') // In 3.1 filter = null is not working
        ;

        if ($files->count() == 0) {
            return 'Nothing to remind';
        }
        $filesByMember = GroupedList::create($files);

        $res = [];

        /* @var $fileByMember ArrayData */
        foreach ($filesByMember->GroupedBy('MemberID') as $fileByMember) {
            $fileByMember = $fileByMember->toMap();

            $MemberID = $fileByMember['MemberID'];
            $files = $fileByMember['Children'];

            $Member = Member::get()->byID($MemberID);
            $sent = $member->sendLegalFilesReminder($files);

            if ($sent) {
                $res[] = 'Reminded Member ' . $MemberID;
            } else {
                $res[] = 'Failed to send email to ' . $MemberID;
            }
        }

        return $res;
    }
}
