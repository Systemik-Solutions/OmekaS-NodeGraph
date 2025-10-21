<?php

namespace NodeGraph;

return [
    'view_manager' => [
        'template_path_stack' => [dirname(__DIR__) . '/view'],
    ],
    'block_layouts' => [
        'invokables' => [
            'Node Graph' => \NodeGraph\Site\BlockLayout\NodeGraphBlock::class,
        ],
    ],
    'form_elements' => [
        'factories' => [
            \NodeGraph\Form\Fieldset\GroupByFieldset::class => \Laminas\ServiceManager\Factory\InvokableFactory::class,
            \NodeGraph\Form\Fieldset\NodeColorsFieldset::class     => \Laminas\ServiceManager\Factory\InvokableFactory::class,
            \NodeGraph\Form\Fieldset\NodeColorPairFieldset::class  => \Laminas\ServiceManager\Factory\InvokableFactory::class,
            \NodeGraph\Form\Fieldset\NodeIconsFieldset::class    => \Laminas\ServiceManager\Factory\InvokableFactory::class,
            \NodeGraph\Form\Fieldset\NodeIconPairFieldset::class => \Laminas\ServiceManager\Factory\InvokableFactory::class,
        ],
    ],
];


// To do: Node colors and icons if preset. remove button wont work
