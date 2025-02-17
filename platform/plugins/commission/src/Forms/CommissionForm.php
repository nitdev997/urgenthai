<?php

namespace Botble\Commission\Forms;

use Botble\Base\Facades\Assets;
use Botble\Base\Forms\FieldOptions\NumberFieldOption;
use Botble\Base\Forms\FieldOptions\TextFieldOption;
use Botble\Base\Forms\Fields\NumberField;
use Botble\Base\Forms\Fields\TextField;
use Botble\Base\Forms\FormAbstract;
use Botble\Commission\Http\Requests\CommissionRequest;
use Botble\Commission\Models\Commission;

class CommissionForm extends FormAbstract
{
    public function setup(): void
    {
        Assets::addScripts(['input-mask']);
        
        $this
            ->setupModel(new Commission())
            ->setValidatorClass(CommissionRequest::class)
            ->add('driver', TextField::class, TextFieldOption::make()->required()->addAttribute('class', 'form-control input-mask-number')->label('Driver (%)')->helperText('On first 10 orders.')->toArray())
            ->add('vendor', TextField::class, TextFieldOption::make()->required()->addAttribute('class', 'form-control input-mask-number')->label('Vendor (%)')->helperText('On every order.')->toArray());
    }
}
