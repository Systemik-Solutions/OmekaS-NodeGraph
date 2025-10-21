<?php

namespace NodeGraph\Form\Fieldset;

use Laminas\Form\Fieldset;
use Laminas\Form\Element\Text;
use Laminas\Form\Element\Button;
use Omeka\Form\Element\ResourceClassSelect;
use Omeka\Form\Element\ResourceTemplateSelect;
use Omeka\Form\Element\PropertySelect;

class NodeColorPairFieldset extends Fieldset
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
            'attributes' => [
                'class' => 'ngc-target ngc--rc',
            ],
        ]);

        // Target: resource template
        $this->add([
            'type' => ResourceTemplateSelect::class,
            'name' => 'resource_template',
            'options' => [
                'label'        => 'Resource template',
                'empty_option' => '',
            ],
            'attributes' => [
                'class' => 'ngc-target ngc--rt',
            ],
        ]);

        // Target: property (term picker)
        $this->add([
            'type' => PropertySelect::class,
            'name' => 'property_value',
            'options' => [
                'label'              => 'Property value',
                'empty_option'       => '',
            ],
            'attributes' => [
                'class' => 'ngc-target ngc--pv chosen-select',
            ],
        ]);

        // Color input
        $this->add([
            'type' => Text::class,
            'name' => 'color',
            'options' => ['label' => 'Color'],
            'attributes' => [
                'type'     => 'color',
                'value'    => '#6699ff',
                'class'    => 'ngc-color',
            ],
        ]);

        // Remove row button 
        $this->add([
            'type' => Button::class,
            'name' => 'remove',
            'options' => ['label' => 'Remove'],
            'attributes' => [
                'type'  => 'button',
                'class' => 'button ngc-remove',
                'style' => 'margin-top:.5rem;',
            ],
        ]);
    }
}
