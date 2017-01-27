<?php

/**
 * A form to manage legal documents of current member
 *
 * This is only for Members, but feel free to extend or duplicate this to support
 * other kinds of owners
 *
 * @author Koala
 */
class LegalFilesForm extends Form
{

    public function __construct($controller, $formName = 'LegalFilesForm', \FieldList $fields = null, \FieldList $actions = null, $validator = null)
    {
        $member = Member::currentUser();

        $types = LegalFile::TypesDatalist('Member');

        $validator = new RequiredFields;

        $fields = new FieldList();

        $uploadSize = LegalFile::getMaxSize();

        $info = _t('LegalFilesForm.VALID_FORMATS', "Valid formats are: {formats}", ['formats' => implode(',', LegalFile::listValidExtensions())]);
        $info .= '<br/>' . _t('LegalFilesForm.MAX_SIZE', "Maximum size is: {size}", ['size' => File::format_size($uploadSize)]);
        $fields->push(new LiteralField('docsInfos', '<div class="message info">' . $info . '</div>'));

        foreach ($types as $type) {
            $name = 'LegalFile[' . $type->ID . ']';
            $title = $type->Title;
            $desc = $type->Description;

            // Add an uploader
            $uploadField = $this->createUploader($name, $title, $desc);
            $uploadField->getValidator()->setAllowedMaxFileSize($uploadSize);
            $fields->push($uploadField);

            // Look if we have a file for this type
            $currentFiles = $member->getLegalFilesByType($type);
            if ($currentFiles->count()) {
                foreach ($currentFiles as $currentFile) {
                    $fields->push(new LiteralField('doc_' . $currentFile->ID . '_info', '<div class="message ' . $currentFile->SilverStripeClass() . '">' . $currentFile->FullStatus() . '</div>'));
                }
            } else {
                // Mandatory
                if ($type->Mandatory) {
                    $validator->addRequiredField($name);
                    $uploadField->addExtraClass('required');
                }
            }
        }

        $actions = new FieldList();
        $actions->push(new FormAction('doSubmit', _t('LegalFilesForm.SUBMIT', 'Submit your files')));


        parent::__construct($controller, $formName, $fields, $actions, $validator);
    }

    /**
     * @param string $name
     * @param string $title
     * @param string $desc
     * @return \FrontendFileField
     */
    protected function createUploader($name, $title, $desc = null)
    {
        $validExtensions = LegalFile::listValidExtensions();

        if (class_exists('FrontendFileField')) {
            $field = new FrontendFileField($name, $title);
            $field->setPreview(true);
        } else {
            $field = FileField::create($name, $title);
        }

        if ($desc) {
            $field->setDescription($desc);
        }

        $field->getValidator()->setAllowedExtensions($validExtensions);
        $field->setFolderName(LegalFile::config()->upload_folder);

        return $field;
    }

    public function doSubmit($data)
    {
        if (empty($data['LegalFile'])) {
            return $this->getController()->redirectBack();
        }

        $member = Member::currentUser();

        $count = 0;
        foreach ($data['LegalFile']['tmp_name'] as $typeID => $tmpName) {
            if (empty($tmpName)) {
                continue;
            }

            $uploadedName = $data['LegalFile']['name'][$typeID];
            $ext = strtolower(pathinfo($uploadedName, PATHINFO_EXTENSION));
            if (!in_array($ext, LegalFile::listValidExtensions())) {
                $errors[] = _t('LegalFilesForm.INVALID_TYPE', "Document {name} has an invalid type", ['name' => $uploadedName]);
                continue;
            }

            $member->addNewLegalFile($typeID, $tmpName, $ext);
            $count++;
        }

        if (!empty($errors)) {
            $this->sessionMessage(implode(";", $errors), "bad");
            return $this->getController()->redirectBack();
        }

        if (!$count) {
            $this->sessionMessage(_t('LegalFilesForm.SUBMIT_NO_NEW', "No new documents have been submitted"), "warning");
        } else {
            if ($member->hasMethod('NotifyNewStatus')) {
                $member->NotifyNewStatus();
            }

            if (LegalFile::config()->validation_workflow) {
                $this->sessionMessage(_t('LegalFilesForm.SUBMIT_OK_VALIDATION', "{count} new documents have been submitted. You will receive a confirmation email once all your files are accepted.", ['count' => $count]), "good");
            } else {
                $this->sessionMessage(_t('LegalFilesForm.SUBMIT_OK', "{count} new documents have been submitted", ['count' => $count]), "good");
            }
        }

        return $this->getController()->redirectBack();
    }
}
