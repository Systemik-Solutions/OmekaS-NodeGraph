<?php

namespace NodeGraph\Form\Fieldset;

use Laminas\Form\Element\Button;
use Laminas\Form\Element\Text;
use Laminas\Form\Fieldset;
use Omeka\Form\Element\ResourceClassSelect;
use Omeka\Form\Element\ResourceTemplateSelect;

/**
 * Fieldset representing a single node icon mapping row.
 */
class NodeIconPairFieldset extends Fieldset
{
    public function init(): void
    {
        $this->add([
            'type' => ResourceClassSelect::class,
            'name' => 'resource_class',
            'options' => [
                'label' => 'Resource class',
                'empty_option' => '',
            ],
            'attributes' => [
                'class' => 'ngc-target ngc--rc',
            ],
        ]);

        $this->add([
            'type' => ResourceTemplateSelect::class,
            'name' => 'resource_template',
            'options' => [
                'label' => 'Resource template',
                'empty_option' => '',
            ],
            'attributes' => [
                'class' => 'ngc-target ngc--rt',
            ],
        ]);

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

        $this->add([
            'type' => Text::class,
            'name' => 'icon',
            'options' => [
                'label' => 'Icon URL or path',
                'info' => 'Use an absolute URL or a root-relative path such as /themes/example/asset/img/icon.svg.',
            ],
            'attributes' => [
                'class' => 'ngi-icon',
                'placeholder' => '/themes/example/asset/img/icon.svg',
            ],
        ]);

        $this->add([
            'type' => Button::class,
            'name' => 'remove',
            'options' => ['label' => 'Remove'],
            'attributes' => [
                'type' => 'button',
                'class' => 'button ngc-remove',
                'style' => 'margin-top:.5rem;',
            ],
        ]);
    }
}
