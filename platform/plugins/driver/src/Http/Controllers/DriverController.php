<?php

namespace Botble\Driver\Http\Controllers;

use Botble\Base\Http\Actions\DeleteResourceAction;
use Botble\Driver\Http\Requests\DriverRequest;
use Botble\Driver\Models\Driver;
use Botble\Base\Http\Controllers\BaseController;
use Botble\Driver\Tables\DriverTable;
use Botble\Driver\Forms\DriverForm;

class DriverController extends BaseController
{
    public function __construct()
    {
        $this
            ->breadcrumb()
            ->add(trans(trans('plugins/driver::driver.name')), route('driver.index'));
    }

    public function index(DriverTable $table)
    {
        $this->pageTitle(trans('plugins/driver::driver.name'));

        return $table->renderTable();
    }

    public function create()
    {
        $this->pageTitle(trans('plugins/driver::driver.create'));

        return DriverForm::create()->renderForm();
    }

    public function store(DriverRequest $request)
    {
        $form = DriverForm::create()->setRequest($request);

        $form->save();

        return $this
            ->httpResponse()
            ->setPreviousUrl(route('driver.index'))
            ->setNextUrl(route('driver.edit', $form->getModel()->getKey()))
            ->setMessage(trans('core/base::notices.create_success_message'));
    }

    public function edit(Driver $driver)
    {
        $this->pageTitle(trans('core/base::forms.edit_item', ['name' => $driver->name]));

        return DriverForm::createFromModel($driver,['driver' => $driver])->renderForm();
    }

    public function update(Driver $driver, DriverRequest $request)
    {
        DriverForm::createFromModel($driver)
            ->setRequest($request)
            ->save();

        return $this
            ->httpResponse()
            ->setPreviousUrl(route('driver.index'))
            ->setMessage(trans('core/base::notices.update_success_message'));
    }

    public function destroy(Driver $driver)
    {
        return DeleteResourceAction::make($driver);
    }
}
