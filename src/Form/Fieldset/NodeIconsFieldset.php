<?php

namespace NodeGraph\Form\Fieldset;

use Laminas\Form\Element\Collection;
use Laminas\Form\Fieldset;

/**
 * Fieldset containing a dynamic collection of node icon mapping rows.
 */
class NodeIconsFieldset extends Fieldset
{
    public function init(): void
    {
        $this->add([
            'type' => Collection::class,
            'name' => 'rows',
            'options' => [
                'label' => 'Node Icons',
                'count' => 0,
                'allow_add' => true,
                'allow_remove' => true,
                'should_create_template' => true,
                'template_placeholder' => '__index__',
                'target_element' => [
                    'type' => NodeIconPairFieldset::class,
                ],
            ],
            'attributes' => [
                'class' => 'node_graph_node_icons',
            ],
        ]);
    }
}
