<?php

class CQRSExtension extends Extension {

    private static $better_buttons_actions = [
        'writeToPayloadStore'
    ];

    private $key;

    public function __construct($key) {
        parent::__construct();

        $this->key = $key;
    }

    private function payloadInterfaceImplemented() {
        return $this->owner instanceof PayloadProvider;
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
        return $this->getPayloadChecksum($this->getCommitedPayload()) === $this->getPayloadChecksum($this->owner->commit());
    }

    public function writeToPayloadStore() {
        if ($this->owner->canCommit()) {
            $handler = $this->getActiveHandler();
            $payload = $this->owner->commit();
            $handler->write($this->getPayloadStoreKey(), $payload);
        }
    }

    public function getActiveHandler() {
        return RedisPayloadStoreHandler::inst();
    }

    public function updateBetterButtonsActions($actions) {
        if (!$this->payloadInterfaceImplemented()) {
            user_error('CQRSExtension requires the owner to implement "PayloadProvider" interface');
        }

        if ($this->owner->canCommit() &&
            !$this->owner->isInSync()) {
            $actions->push(
                BetterButtonCustomAction::create(
                    'writeToPayloadStore',
                    _t('CQRSExtension.WRITE', 'Lesedatenbank aktualisieren')
                )
            );
        }
    }
}