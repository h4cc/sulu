<?php

namespace DTL\Bundle\ContentBundle\Form\Type\Property;

use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use DTL\Component\Content\Property\PropertyTypeInterface;
use DTL\Component\Content\FrontView\FrontView;
use DTL\Bundle\ContentBundle\Form\EventListener\ResizeableListener;

/**
 * This type wraps the other content types and makes them resizable.
 *
 * All content properties in Sulu are capable of being "multiple". i.e.
 * when max_occurs > 1.
 *
 * This means that we must wrap all of the form types in a resizeable form
 * type, which knows when a property is multiple and is then capable
 * of mapping the additional incoming data.
 *
 * @author Daniel Leech <daniel@dantleech.com>
 */
class CollectionType extends AbstractType implements PropertyTypeInterface
{
    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'min_occurs' => 1,
            'max_occurs' => 1,
            'multiple' => function (Options $options) {
                return $options['min_occurs'] !== $options['max_occurs'];
            },
            'compound' => true,
        ));

        $resolver->setRequired(array(
            'type',
            'options',
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $prototype = $builder->create($builder->getName(), $options['type'], $options['options']);
        $builder->setAttribute('prototype', $prototype->getForm());

        $resizeListener = new ResizeableListener(
            $options['type'],
            $options['options'],
            $options['multiple']
        );

        $builder->addEventSubscriber($resizeListener);
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['multiple'] = $options['multiple'];
        $view->vars['prototype'] = $form->getConfig()->getAttribute('prototype')->createView($view);
    }

    /**
     * {@inheritDoc}
     */
    public function buildFrontView(FrontView $view, $data, array $options)
    {
        $view->setValue($data);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'collection';
    }
}
