<?php

namespace NodeGraph\Form\Fieldset;

use Laminas\Form\Fieldset;
use Laminas\Form\Element\Text;
use Laminas\Form\Element\Button;
use Omeka\Form\Element\ResourceClassSelect;
use Omeka\Form\Element\ResourceTemplateSelect;

/**
 * Fieldset representing a single node color mapping row (target selector + color picker).
 */
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

        // Target: the actual value of the property selected in "Group by".
        $this->add([
            'type' => Text::class,
            'name' => 'property_value',
            'options' => [
                'label' => 'Property value',
                'info' => 'Enter the exact value used by items in this group.',
            ],
            'attributes' => [
                'class' => 'ngc-target ngc--pv',
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
