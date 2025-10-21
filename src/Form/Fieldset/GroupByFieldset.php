<?php
namespace NodeGraph\Form\Fieldset;

use Laminas\Form\Fieldset;
use Laminas\Form\Element\Select;
use Omeka\Form\Element\PropertySelect;

class GroupByFieldset extends Fieldset
{
    public function init(): void
    {
        $this->add([
            'type' => Select::class,
            'name' => 'group-by-select',
            'options' => [
                'label' => 'Group by', // @translate
                'value_options' => [
                    'resource_class'    => 'Resource class',     // @translate
                    'resource_template' => 'Resource template',  // @translate
                    'property_value'    => 'Property value',     // @translate
                ],
            ],
            'attributes' => [
                'class' => 'node_graph_group_by_select',
            ],
        ]);

        $this->add([
            'type' => PropertySelect::class,
            'name' => 'group-by-property-select',
            'options' => [
                'label'              => 'Property', // @translate
                'empty_option'       => '',
                'term_as_value'      => true,
                'use_hidden_element' => true,
            ],
            'attributes' => [
                'class' => 'node_graph_group_by_property',
            ],
        ]);
    }
}