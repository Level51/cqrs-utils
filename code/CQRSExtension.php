<?php

/**
 * Class CQRSExtension
 */
class CQRSExtension extends Extension {

    private static $better_buttons_actions = [
        'writeToPayloadStore'
    ];

    /**
     * @var string
     */
    private $key;

    /**
     * @var PayloadManifestParser
     */
    private $parser;

    /**
     * CQRSExtension constructor.
     *
     * @param $key string
     */
    public function __construct($key) {
        parent::__construct();

        $this->key = $key;
        $this->parser = new PayloadManifestParser();
    }

    /**
     * @return PayloadStoreHandler
     */
    public function getActiveHandler() {
        return RedisPayloadStoreHandler::inst();
    }

    /**
     * @return string
     */
    private function getPayloadStoreKey() {
        return $this->owner->{$this->key} ?: $this->key;
    }

    /**
     * Obtains the commited payload
     *
     * @return array
     */
    private function getCommitedPayload() {
        return $this->getActiveHandler()->read($this->getPayloadStoreKey());
    }

    /**
     * Generates a MD5 hash of the given payload.
     *
     * @param $payload
     *
     * @return string
     */
    private function getPayloadChecksum($payload) {
        return md5(Convert::array2json($payload));
    }

    /**
     * Checks if the current data is equal to the commited payload.
     *
     * @return bool
     */
    public function isInSync() {
        return $this->getPayloadChecksum($this->getCommitedPayload()) ===
            $this->getPayloadChecksum($this->parser->commit($this->owner, false));
    }

    public function updateCMSActions($actions) {
        if ($this->owner->canEdit()) {

            // Add "save" action
            $actions->push(FormAction::create('save', _t('CMSMain.SAVE'))
                ->addExtraClass('ss-ui-action-constructive')->setAttribute('data-icon', 'accept'));

            // Add action for writing data to payload store
            if (Permission::check('PUBLISH_' . mb_strtoupper($this->owner->class))) {
                $updateAction = FormAction::create(
                    'writeToPayloadStore',
                    _t('CQRSExtension.WRITE', 'Lesedatenbank aktualisieren')
                )->setDisabled($this->isInSync());

                if ($this->isInSync()) {
                    $updateAction->setDescription(_t('CQRSExtension.UP_TO_DATE', 'Lesedatenbank ist aktuell'));
                }

                $actions->push($updateAction);
            }
        }
    }

    /**
     * Writes the commited payload to the current payload store.
     *
     * @return bool successful or not
     */
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

    /**
     * Provides BetterButtonsAction for Super-Admin View.
     *
     * TODO: Disabled state not working when in super admin view
     *
     * @param $actions
     */
    public function updateBetterButtonsActions($actions) {
        $updateAction = BetterButtonCustomAction::create(
            'writeToPayloadStore',
            _t('CQRSExtension.WRITE', 'Lesedatenbank aktualisieren')
        )->setDisabled($this->isInSync());

        if ($this->isInSync()) {
            $updateAction->
            $updateAction->setDescription(_t('CQRSExtension.UP_TO_DATE', 'Lesedatenbank ist aktuell'));
        }

        $actions->push($updateAction);
    }

    /**
     * Renders a list of validation errors.
     *
     * @return mixed
     */
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