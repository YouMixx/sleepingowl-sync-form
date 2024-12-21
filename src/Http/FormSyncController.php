<?php

declare(strict_types=1);

namespace YouMixx\SleepingowlSyncForm\Http;

use AdminForm;
use AdminFormElement;
use AdminSection;
use Exception;
use Illuminate\Http\JsonResponse;
use SleepingOwl\Admin\Contracts\ModelConfigurationInterface;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SleepingOwl\Admin\Form\Buttons\FormButton;
use YouMixx\SleepingowlSyncForm\Objects\SyncableObject;
use YouMixx\SleepingowlSyncForm\Service\SycnableService;

class FormSyncController
{

    public function massSycnableHandler(ModelConfigurationInterface $model, Request $request)
    {
        $request->validate([
            'elements' => 'required|array',
        ]);

        $elements = $request->get('elements');
        $items = $model->getRepository()->getQuery()->whereIn('id', $elements)->get();

        $sycnable = new SycnableService($model);
        $sycnable->setItems($items)->syncable();

        if ($sycnable->isFailed()) {
            return redirect()->back()->with([
                'result' => $sycnable->getResult(),
                'error_message' => 'Произошла ошибка'
            ]);
        }

        return redirect()->back()->with([
            'result' => $sycnable->getResult(),
            'success_message' => 'Синхронизация прошла успешно'
        ]);
    }

    public function massSycnableForm(ModelConfigurationInterface $model)
    {
        $areaResult = $this->areaResult($model);

        $form = AdminForm::card()
            ->setAction(route('admin.model.mass-sync', $model->getAlias()))
            ->addBody([
                AdminFormElement::multiselect('elements', 'Элементы')
                    ->setModelForOptions($model->getClass(), 'name'),
                ...$areaResult
            ]);

        $form->setModelClass($model->getClass());
        $form->getButtons()->setButtons([
            'submit' => $this->button(),
        ]);

        $form->initialize();

        return AdminSection::view($form, 'Массовая синхронизация');
    }

    private function button()
    {
        $btn = new FormButton();
        $btn->setText('Синхронизировать');
        $btn->setHtmlAttributes([
            'type' => 'submit',
            'class' => 'btn btn-primary',
        ]);

        return $btn;
    }

    private function areaResult(ModelConfigurationInterface $model)
    {
        $result = session()->get('result', []);
        $text = "";

        foreach ($result as $elementId => $urls) {
            $element = $model->getRepository()->find($elementId);

            $text .= "{$element->name}:\n";
            foreach ($urls as $url => $result) {
                if ($result['status'] == true) {
                    $text .= "$url успешно\n";
                } else {
                    $text .= "$url ошибка: {$result['message']}\n";
                }
            }

            $text .= "\n";
        }

        return [
            AdminFormElement::textarea('resultat', 'Результат')
                ->setExactValue($text)
                ->setReadonly(true)
        ];
    }
}
