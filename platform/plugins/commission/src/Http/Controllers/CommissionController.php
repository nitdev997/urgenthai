<?php

namespace Botble\Commission\Http\Controllers;

use Botble\Base\Http\Actions\DeleteResourceAction;
use Botble\Commission\Http\Requests\CommissionRequest;
use Botble\Commission\Models\Commission;
use Botble\Base\Http\Controllers\BaseController;
use Botble\Commission\Tables\CommissionTable;
use Botble\Commission\Forms\CommissionForm;

class CommissionController extends BaseController
{
    public function __construct()
    {
        $this
            ->breadcrumb()
            ->add(trans(trans('plugins/commission::commission.name')), route('commission.index'));
    }

    public function index(CommissionTable $table)
    {
        $this->pageTitle(trans('plugins/commission::commission.name'));

        return $table->renderTable();
    }

    public function create()
    {
        $this->pageTitle(trans('plugins/commission::commission.create'));

        return CommissionForm::create()->renderForm();
    }

    public function store(CommissionRequest $request)
    {
        $form = CommissionForm::create()->setRequest($request);

        $form->save();

        return $this
            ->httpResponse()
            ->setPreviousUrl(route('commission.index'))
            ->setNextUrl(route('commission.edit', $form->getModel()->getKey()))
            ->setMessage(trans('core/base::notices.create_success_message'));
    }

    public function edit(Commission $commission)
    {
        $this->pageTitle(trans('edit', ['vendor' => $commission->vendor]));

        return CommissionForm::createFromModel($commission)->renderForm();
    }

    public function update(Commission $commission, CommissionRequest $request)
    {
        CommissionForm::createFromModel($commission)
            ->setRequest($request)
            ->save();

        return $this
            ->httpResponse()
            ->setPreviousUrl(route('commission.index'))
            ->setMessage(trans('core/base::notices.update_success_message'));
    }

    public function destroy(Commission $commission)
    {
        return DeleteResourceAction::make($commission);
    }
}
