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
            ->filter('ExpirationDate:LessThan',
                date('Y-m-d', strtotime('+'.$days.' days')))
            ->exclude('MemberID', 0)
            ->filter('Reminded', null)
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
            $files    = $fileByMember['Children'];

            $Member = Member::get()->byID($MemberID);

            $email = new Email();
            $email->setSubject(_t('LegalFiles.EMAIL_SUBJECT',
                    "Legal documents are about to be expired"));

            $viewer = new SSViewer('LegalFilesReminder');
            $result = $viewer->process($Member, array('Files' => $files));
            $body   = (string) $result;

            $email->setBody($body);
            $email->setTo($Member->Email);

            $sent = $email->send();

            if ($sent) {
                foreach ($files as $file) {
                    $file->Reminded = date('Y-m-d H:i:s');
                    $file->write();
                }

                $res[] = 'Reminded Member '.$MemberID;
            } else {
                $res[] = 'Failed to send email to '.$MemberID;
            }
        }

        return $res;
    }
}