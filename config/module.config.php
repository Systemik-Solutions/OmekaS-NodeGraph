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
        ],
    ],
    'router' => [
        'routes' => [
            'node-graph-ajax' => [
                'type' => \Laminas\Router\Http\Segment::class,
                'options' => [
                    'route' => '/node-graph/ajax/:action',
                    'defaults' => [
                        'controller' => \NodeGraph\Controller\AjaxController::class,
                        'action'     => 'popup-extra',
                    ],
                ],
            ],
        ],
    ],

    // + controller factory
    'controllers' => [
        'factories' => [
             \NodeGraph\Controller\AjaxController::class => \Laminas\ServiceManager\Factory\InvokableFactory::class,
        ],
    ],
];

