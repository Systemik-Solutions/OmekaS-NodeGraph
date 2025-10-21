<?php

namespace NodeGraph\Form\Fieldset;

use Laminas\Form\Fieldset;
use Laminas\Form\Element\Select;
use Laminas\Form\Element\Button;
use Omeka\Form\Element\ResourceClassSelect;
use Omeka\Form\Element\ResourceTemplateSelect;
use Omeka\Form\Element\PropertySelect;


class NodeIconPairFieldset extends Fieldset
{
    public function init(): void
    {
       // Target: resource class
        $this->add([
            'type' => ResourceClassSelect::class,
            'name' => 'resource_class',
            'options' => [
                'label'        => 'Resource class',
                'empty_option' => '',
            ],
            'attributes' => ['class' => 'ngi-target ngi--rc'],
        ]);

        // Target: resource template
        $this->add([
            'type' => ResourceTemplateSelect::class,
            'name' => 'resource_template',
            'options' => [
                'label'        => 'Resource template',
                'empty_option' => '',
            ],
            'attributes' => ['class' => 'ngi-target ngi--rt'],
        ]);

        // Target: property (term picker)
        $this->add([
            'type' => PropertySelect::class,
            'name' => 'property_value',
            'options' => [
                'label' => 'Property value',
            ],
            'attributes' => [
                'class' => 'ngi-target ngi--pv',
            ],
        ]);

        // Icon select . TODO   fix by online url
        $this->add([
            'type' => Select::class,
            'name' => 'icon',
            'options' => [
                'label' => 'Icon',
                'empty_option' => 'Select an icon…',
                'value_options' => [
                    'fas fa-book'         => 'Book',
                    'fas fa-image'        => 'Image',
                    'fas fa-file-alt'     => 'File',
                    'fas fa-tag'          => 'Tag',
                    'fas fa-user'         => 'User',
                    'fas fa-users'        => 'Users',
                    'fas fa-map-marker-alt' => 'Marker',
                    'fas fa-university'   => 'Institution',
                    'fas fa-landmark'     => 'Landmark',
                    'fas fa-folder'       => 'Folder',
                    'fas fa-archive'      => 'Archive',
                    'custom'              => 'Custom (enter class below)',
                ],
            ],
            'attributes' => [
                'class' => 'chosen-select ngi-icon-select',
                'data-placeholder' => 'Select an icon…',
            ],
        ]);


        // Remove row button 
        $this->add([
            'type' => Button::class,
            'name' => 'remove',
            'options' => ['label' => 'Remove'],
            'attributes' => [
                'type'  => 'button',
                'class' => 'button ngi-remove',
                'style' => 'margin-top:.5rem;',
            ],
        ]);
    }
}
