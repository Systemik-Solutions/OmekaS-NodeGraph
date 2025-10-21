<?php

namespace NodeGraph\Form\Fieldset;

use Laminas\Form\Fieldset;
use Laminas\Form\Element\Collection;

class NodeColorsFieldset extends Fieldset
{
    public function init(): void
    {
        $this->add([
            'type' => Collection::class,
            'name' => 'rows',
            'options' => [
                'label' => 'Node Colors', 
                'count' => 0,
                'allow_add' => true,
                'allow_remove' => true,
                'should_create_template' => true,
                'template_placeholder' => '__index__',
                'target_element' => [
                    'type' => \NodeGraph\Form\Fieldset\NodeColorPairFieldset::class,
                ],
            ],
            'attributes' => [
                'class' => 'node_graph_node_colors',
            ],
        ]);
    }
}
