<?php

class CQRSExtension extends Extension {

    private static $better_buttons_actions = [
        'writeToPayloadStore'
    ];

    private $key;

    private $parser;

    public function __construct($key) {
        parent::__construct();

        $this->key = $key;
        $this->parser = new PayloadManifestParser();
    }

    public function getActiveHandler() {
        return RedisPayloadStoreHandler::inst();
    }

    private function getPayloadStoreKey() {
        return $this->owner->{$this->key} ?: $this->key;
    }

    private function getCommitedPayload() {
        return $this->getActiveHandler()->read($this->getPayloadStoreKey());
    }

    private function getPayloadChecksum($payload) {
        return md5(Convert::array2json($payload));
    }

    public function isInSync() {
        return $this->getPayloadChecksum($this->getCommitedPayload()) ===
            $this->getPayloadChecksum($this->owner->commit());
    }

    public function updateCMSActions($actions) {
        if ($this->owner->canEdit()) {

            // Add "save" action
            $actions->push(FormAction::create('save', _t('CMSMain.SAVE'))
                ->addExtraClass('ss-ui-action-constructive')->setAttribute('data-icon', 'accept'));

            // Add action for writing data to payload store
            // TODO: Check for classes and icon
            if (Permission::check('PUBLISH_' . mb_strtoupper($this->owner->class))) {
                $actions->push(FormAction::create('writeToPayloadStore', _t('CQRSExtension.WRITE', 'Lesedatenbank aktualisieren')));
            }
        }
    }

    public function writeToPayloadStore() {
        $payload = $this->parser->commit($this->owner);

        if ($this->parser->canCommit()) {
            $handler = $this->getActiveHandler();
            $handler->write($this->getPayloadStoreKey(), $payload);

            return true;
        } else {
            Session::set(
                "FormInfo.Form_ItemEditForm.formError.message",
                $this->getErrorMessageForTemplate()
            );
            Session::set("FormInfo.Form_ItemEditForm.formError.type", "bad");

            return false;
        }
    }

    public function updateBetterButtonsActions($actions) {
        $actions->push(BetterButtonCustomAction::create(
            'writeToPayloadStore',
            _t('CQRSExtension.WRITE', 'Lesedatenbank aktualisieren')
        ));
    }

    public function getErrorMessageForTemplate() {
        return $this->owner->customise([
            'ValidationErrors' => ArrayList::create(array_map(function ($error) {
                return [
                    "value" => $error
                ];
            }, $this->parser->getValidationErrors()))
        ])->renderWith('CQRSErrorMessage');
    }
}