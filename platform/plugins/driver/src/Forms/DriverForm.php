<?php

namespace Botble\Driver\Forms;

use Botble\Base\Forms\FieldOptions\NameFieldOption;
use Botble\Base\Forms\FieldOptions\StatusFieldOption;
use Botble\Base\Forms\Fields\SelectField;
use Botble\Base\Forms\Fields\TextField;
use Botble\Base\Forms\FormAbstract;
use Botble\Driver\Http\Requests\DriverRequest;
use Botble\Driver\Models\Driver;
use Illuminate\Support\Facades\Storage;

class DriverForm extends FormAbstract
{
    public function setup(): void
    {
        $driver = $this->getModel();
        $this
        ->setupModel(new Driver())
        ->setValidatorClass(DriverRequest::class)
        ->withCustomFields()
        ->add('name', 'text', [
            'label' => trans('core/base::forms.name'),
            'required' => true,
            'attr' => [
                'placeholder' => trans('Enter City Name'),
                'data-counter' => 120,
            ],
        ])
        ->add('email', 'text', [
            'label' => trans('Email address'),
            'required' => true,
            'attr' => [
                'placeholder' => trans('Enter Email address'),
                'data-counter' => 20,
            ],
        ])
        ->add('phone', 'number', [
            'label' => trans('Phone number'),
            'required' => true,
            'attr' => [
                'placeholder' => trans('Enter Phone number'),
                'data-counter' => 10,
            ],
        ])
        ->add('address', 'text', [
            'label' => trans('Address'),
            'required' => true,
            'attr' => [
                'placeholder' => trans('Enter Address'),
                'data-counter' => 200,
            ],
        ])
        ->add('dl_number', 'text', [
            'label' => trans('Driving Licence Number'),
            'required' => true,
            'attr' => [
                'placeholder' => trans('Enter Driving Licence Number'),
                'data-counter' => 15,
            ],
        ])
        ->add('country', 'text', [
            'label' => trans('Country'),
            'required' => true,
            'attr' => [
                'placeholder' => trans('Enter Country Name'),
                'data-counter' => 8,
            ],
        ])
        // ->add('dl_image', 'file', [
        //     'label' => trans('Driving Licence Image'),
        //     'required' => true,
        //     'attr' => [
        //         'accept' => 'image/*', // Restrict selection to image files
        //         'placeholder' => trans('Upload Driving Licence Image'),
        //     ],
        // ])
        
        // ->add('number_plate_image', 'file', [
        //     'label' => trans('Number Plate Image'),
        //     'required' => true,
        //     'attr' => [
        //         'accept' => 'image/*',
        //         'placeholder' => trans('Upload Number Plate Image'),
        //     ],
        // ])
        
        ->add('document_verification_status', 'customSelect', [
            'label' => trans('Document Verification Status'),
            'required' => true,
            'choices' => [
                'Pending' => trans('Pending'),
                'Complete' => trans('Complete'),
            ],
            'attr' => [
                'placeholder' => trans('Document Verification Status'),
            ],
        ])
        ->add('download_np_image', 'html', [
            'html' => '<a href="' . Storage::url($driver->number_plate_image) . '" download="'.$driver->name.'-NP" class="text-decoration-none">
                            <img src="' . (in_array(strtolower(pathinfo(Storage::path($driver->number_plate_image), PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff']) ? Storage::url($driver->number_plate_image) : Storage::url('driver/file.png')) . '" width="100" height="100" class="object-fit-contain border rounded" title="Number Plate"/>
                       </a>',
        ])
        ->add('download_dl_image', 'html', [
            'html' => '<a href="' . Storage::url($driver->dl_image) . '" download="'.$driver->name.'-DL" class="text-decoration-none">
                            <img src="' . Storage::url($driver->dl_image) . '" width="100" height="100" class="object-fit-contain border rounded" title="Driving Licence"/>
                       </a>',
        ])
        ->setBreakFieldPoint('status');
    }
}
