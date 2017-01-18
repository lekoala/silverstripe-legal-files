<?php

/**
 * LegalFileEmail
 *
 * @author Kalyptus SPRL <thomas@kalyptus.be>
 */
class LegalFileEmail
{

    /**
     * A little helper to retrieve an email and support EmailTemplate module
     *
     * @param DataObject $dataobject
     * @param string $title
     * @param string $template
     * @param array $templateData
     * @return \Email
     */
    public static function getEmail($dataobject, $title, $template, $templateData = null)
    {
        $email = null;

        $owner = null;
        $rcpt = $dataobject->Email;
        if ($dataobject instanceof LegalFile) {
            $owner = $dataobject->OwnerObject();
            $rcpt = $owner->Email;
        }

        if (class_exists('EmailTemplate')) {
            $code = strtolower(preg_replace('/([a-zA-Z])(?=[A-Z])/', '$1-', $template));
            $code = preg_replace('/-email$/', '', $code);

            $email = EmailTemplate::getEmailByCode($code);
            if ($email) {
                $email->populateTemplate($dataobject);
                if (!empty($templateData)) {
                    $email->populateTemplate($templateData);
                }
            }
        }

        if (!$email || !$email->body) {
            $email = new Email();
            $viewer = new SSViewer('email/' . $template);
            $result = $viewer->process($dataobject, $templateData);
            $body = (string) $result;
            $email->setBody($body);
        }

        $email->setSubject($title);

        if ($rcpt) {
            $email->setTo($rcpt);
        }

        return $email;
    }
}
