<?php

/*
 * This file is part of the Symfony CMF package.
 *
 * (c) 2011-2015 Symfony CMF
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Cmf\Bundle\SonataAdminIntegrationBundle\Admin\Routing;

use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\CoreBundle\Form\Type\ImmutableArrayType;
use Sonata\DoctrinePHPCRAdminBundle\Admin\Admin;
use Sonata\DoctrinePHPCRAdminBundle\Form\Type\TreeModelType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Cmf\Bundle\RoutingBundle\Model\Route;
use Symfony\Cmf\Bundle\RoutingBundle\Form\Type\RouteTypeType;
use PHPCR\Util\PathHelper;

class RouteAdmin extends Admin
{
    protected $translationDomain = 'CmfRoutingBundle';

    /**
     * Root path for the route parent selection.
     *
     * @var string
     */
    protected $routeRoot;

    /**
     * Root path for the route content selection.
     *
     * @var string
     */
    protected $contentRoot;

    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper->addIdentifier('path', 'text');
    }

    protected function configureFormFields(FormMapper $formMapper)
    {
        $formMapper
            ->tab('form.tab_general')
                ->with('form.group_location', ['class' => 'col-md-3'])
                    ->add(
                        'parentDocument',
                        TreeModelType::class,
                        ['choice_list' => [], 'select_root_node' => true, 'root_node' => $this->routeRoot]
                    )
                    ->add('name', TextType::class)
                ->end() // group location

                ->ifTrue(null === $this->getParentFieldDescription())
                    ->with('form.group_general', ['class' => 'col-md-9'])
                        ->add(
                            'content',
                            TreeModelType::class,
                            ['choice_list' => [], 'required' => false, 'root_node' => $this->contentRoot]
                        )
                    ->end() // group general
                ->end() // tab general

                ->tab('form.tab_routing')
                    ->with('form.group_path', ['class' => 'col-md-6'])
                        ->add(
                            'variablePattern',
                            TextType::class,
                            ['required' => false],
                            ['help' => 'form.help_variable_pattern']
                        )
                        ->add(
                            'options',
                            ImmutableArrayType::class,
                            ['keys' => $this->configureFieldsForOptions($this->getSubject()->getOptions())],
                            ['help' => 'form.help_options']
                        )
                    ->end() // group path

                    ->with('form.group_data', ['class' => 'col-md-6'])
                        ->add(
                            'defaults',
                            ImmutableArrayType::class,
                            ['keys' => $this->configureFieldsForDefaults($this->getSubject()->getDefaults())]
                        )
                    ->end() // group data
                ->ifEnd()

            ->end(); // tab general/routing
    }

    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $datagridMapper->add('name', 'doctrine_phpcr_nodename');
    }

    public function setRouteRoot($routeRoot)
    {
        // make limitation on base path work
        parent::setRootPath($routeRoot);
        // TODO: fix widget to show root node when root is selectable
        // https://github.com/sonata-project/SonataDoctrinePhpcrAdminBundle/issues/148
        $this->routeRoot = PathHelper::getParentPath($routeRoot);
    }

    public function setContentRoot($contentRoot)
    {
        $this->contentRoot = $contentRoot;
    }

    public function getExportFormats()
    {
        return array();
    }

    /**
     * Provide default route defaults and extract defaults from $dynamicDefaults.
     *
     * @param array $dynamicDefaults
     *
     * @return array Value for sonata_type_immutable_array
     */
    protected function configureFieldsForDefaults($dynamicDefaults)
    {
        $defaults = array(
            '_controller' => array('_controller', TextType::class, array('required' => false)),
            '_template' => array('_template', TextType::class, array('required' => false)),
            'type' => array('type', RouteTypeType::class, array(
                'placeholder' => '',
                'required' => false,
            )),
        );

        foreach ($dynamicDefaults as $name => $value) {
            if (!isset($defaults[$name])) {
                $defaults[$name] = array($name, TextType::class, array('required' => false));
            }
        }

        //parse variable pattern and add defaults for tokens - taken from routecompiler
        /** @var $route Route */
        $route = $this->subject;
        if ($route && $route->getVariablePattern()) {
            preg_match_all('#\{\w+\}#', $route->getVariablePattern(), $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
            foreach ($matches as $match) {
                $name = substr($match[0][0], 1, -1);
                if (!isset($defaults[$name])) {
                    $defaults[$name] = array($name, TextType::class, array('required' => true));
                }
            }
        }

        if ($route && $route->getOption('add_format_pattern')) {
            $defaults['_format'] = array('_format', TextType::class, array('required' => true));
        }
        if ($route && $route->getOption('add_locale_pattern')) {
            $defaults['_locale'] = array('_locale', TextType::class, array('required' => false));
        }

        return $defaults;
    }

    /**
     * Provide default options and extract options from $dynamicOptions.
     *
     * @param array $dynamicOptions
     *
     * @return array Value for sonata_type_immutable_array
     */
    protected function configureFieldsForOptions(array $dynamicOptions)
    {
        $options = array(
            'add_locale_pattern' => array('add_locale_pattern', CheckboxType::class, array('required' => false, 'label' => 'form.label_add_locale_pattern', 'translation_domain' => 'CmfRoutingBundle')),
            'add_format_pattern' => array('add_format_pattern', CheckboxType::class, array('required' => false, 'label' => 'form.label_add_format_pattern', 'translation_domain' => 'CmfRoutingBundle')),
            'add_trailing_slash' => array('add_trailing_slash', CheckboxType::class, array('required' => false, 'label' => 'form.label_add_trailing_slash', 'translation_domain' => 'CmfRoutingBundle')),
        );

        foreach ($dynamicOptions as $name => $value) {
            if (!isset($options[$name])) {
                $options[$name] = array($name, TextType::class, array('required' => false));
            }
        }

        return $options;
    }

    public function prePersist($object)
    {
        $defaults = array_filter($object->getDefaults());
        $object->setDefaults($defaults);
    }

    public function preUpdate($object)
    {
        $defaults = array_filter($object->getDefaults());
        $object->setDefaults($defaults);
    }

    public function toString($object)
    {
        return $object instanceof Route && $object->getId()
            ? $object->getId()
            : $this->trans('link_add', array(), 'SonataAdminBundle')
        ;
    }
}
