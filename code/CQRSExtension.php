<?php

/**
 * Class CQRSExtension
 * TODO: Better Buttons Actions return "1" on success
 * TODO: Reload logic when CQRS actions are applied to reflect new state immediately
 */
class CQRSExtension extends Extension {

    private static $better_buttons_actions = [
        'writeToPayloadStore'
    ];

    /**
     * @var string Default config for payload store handler type
     */
    private static $payload_store_handler_type = RedisPayloadStoreHandler::class;

    /**
     * @var string
     */
    private $key;

    /**
     * @var PayloadManifestParser
     */
    private $parser;

    /**
     * @var PayloadStoreHandler
     */
    private $payloadStoreHandler;

    /**
     * CQRSExtension constructor.
     *
     * @param $key    string
     * @param $config array optional config object for store handler etc.
     */
    public function __construct($key, $config = null) {
        parent::__construct();
        $payloadStoreHandlerType = Config::inst()->get($this->class, 'payload_store_handler_type');

        $this->key = $key;
        $this->parser = new PayloadManifestParser();
        $this->payloadStoreHandler = new $payloadStoreHandlerType($config);
    }

    /**
     * @return PayloadStoreHandler
     */
    public function getActiveHandler() {
        return $this->payloadStoreHandler;
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
        return md5(Convert::array2json($payload, JSON_NUMERIC_CHECK, JSON_UNESCAPED_SLASHES));
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
            if (Director::is_cli()) {
                Debug::dump($this->parser->getValidationErrors());
                $this->parser->clearValidationErrors();
            } else {
                Session::set(
                    "FormInfo.Form_ItemEditForm.formError.message",
                    $this->getErrorMessageForTemplate()
                );
                Session::set("FormInfo.Form_ItemEditForm.formError.type", "bad");
            }

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
