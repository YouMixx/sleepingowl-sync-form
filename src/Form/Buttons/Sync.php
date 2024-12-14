<?php

namespace YouMixx\SleepingowlSyncForm\Form\Buttons;

use SleepingOwl\Admin\Form\Buttons\FormButton;

class Sync extends FormButton
{
    protected $show = true;
    protected $name = 'sync';
    protected $iconClass = 'fas fa-sync';

    public function __construct()
    {
        $this->setText('Синхронизировать');
    }

    /**
     * Init Save Button.
     */
    public function initialize()
    {
        parent::initialize();

        $this->setUrl(route('admin.model.sync', [
            $this->getModelConfiguration()->getAlias(),
            $this->getModel()->getKey()
        ]));
        
        $this->setHtmlAttributes($this->getHtmlAttributes() + [
            'name' => 'next_action',
            'class' => 'btn btn-destroy',
        ]);
    }

    /**
     * Show policy.
     *
     * @return bool
     */
    public function canShow()
    {
        if (is_null($this->getModel()->getKey())) {
            return false;
        }

        $this->show = ! $this->isTrashed() && $this->getModelConfiguration()->isSyncable($this->getModel());

        return parent::canShow();
    }
}
