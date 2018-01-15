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

    public function writeToPayloadStore() {
        $payload = $this->parser->commit($this->owner);

        if ($this->parser->canCommit()) {
            $handler = $this->getActiveHandler();
            $handler->write($this->getPayloadStoreKey(), $payload);
        } else {
            Session::set(
                "FormInfo.Form_ItemEditForm.formError.message",
                "Testing" // TODO: Message here
            );
            Session::set("FormInfo.Form_ItemEditForm.formError.type", "bad");
        }
    }

    /*public function updateBetterButtonsActions($actions) {
        $actions->push(
            BetterButtonCustomAction::create(
                'writeToPayloadStore',
                _t('CQRSExtension.WRITE', 'Lesedatenbank aktualisieren')
            )
        );

       var_dump($this->prepareReadPayload());
        die;

        if ($this->owner->canCommit() &&
            !$this->owner->isInSync()) {

        }
    }*/
}