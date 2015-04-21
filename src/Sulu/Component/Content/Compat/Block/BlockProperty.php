<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Component\Content\Compat\Block;

use JMS\Serializer\Annotation\Discriminator;
use JMS\Serializer\Annotation\HandlerCallback;
use JMS\Serializer\Annotation\Type;
use JMS\Serializer\Context;
use JMS\Serializer\JsonDeserializationVisitor;
use JMS\Serializer\JsonSerializationVisitor;
use Sulu\Component\Content\Compat\Property;
use Sulu\Component\Content\Compat\PropertyInterface;

/**
 * Representation of a block node in template xml
 */
class BlockProperty extends Property implements BlockPropertyInterface
{
    /**
     * properties managed by this block
     * @var BlockPropertyType[]
     */
    private $types = array();

    /**
     * @var BlockPropertyType[]
     */
    private $properties = array();

    /**
     * @var string
     */
    private $defaultTypeName;

    public function __construct(
        $name,
        $metadata,
        $defaultTypeName,
        $mandatory = false,
        $multilingual = false,
        $maxOccurs = 1,
        $minOccurs = 1,
        $params = array(),
        $tags = array(),
        $col = null
    ) {
        parent::__construct(
            $name,
            $metadata,
            'block',
            $mandatory,
            $multilingual,
            $maxOccurs,
            $minOccurs,
            $params,
            $tags,
            $col
        );

        $this->defaultTypeName = $defaultTypeName;
    }

    /**
     * returns a list of properties managed by this block
     * @return BlockPropertyType[]
     */
    public function getTypes()
    {
        return $this->types;
    }

    /**
     * adds a type
     * @param BlockPropertyType $type
     */
    public function addType(BlockPropertyType $type)
    {
        $this->types[$type->getName()] = $type;
    }

    /**
     * returns type with given name
     * @param string $name of property
     * @throws \InvalidArgumentException
     * @return BlockPropertyType
     */
    public function getType($name)
    {
        if (!isset($this->types[$name])) {
            throw new \InvalidArgumentException(
                sprintf(
                    'The block type "%s" has not been registered. Known block types are: [%s]',
                    $name,
                    implode(', ', array_keys($this->types))
                )
            );
        }

        return $this->types[$name];
    }

    /**
     * return default type name
     * @return string
     */
    public function getDefaultTypeName()
    {
        return $this->defaultTypeName;
    }

    /**
     * returns child properties of given Type
     * @param string $typeName
     * @return PropertyInterface[]
     */
    public function getChildProperties($typeName)
    {
        return $this->getType($typeName)->getChildProperties();
    }

    /**
     * initiate new child with given type name
     * @param integer $index
     * @param string $typeName
     * @return BlockPropertyType
     */
    public function initProperties($index, $typeName)
    {
        $type = $this->getType($typeName);
        $this->properties[$index] = clone($type);

        return $this->properties[$index];
    }

    /**
     * clears all initialized properties
     */
    public function clearProperties()
    {
        $this->properties = array();
    }

    /**
     * returns properties for given index
     * @param integer $index
     * @return BlockPropertyType
     */
    public function getProperties($index)
    {
        if (!isset($this->properties[$index])) {
            throw new \OutOfRangeException(sprintf(
                'No properties at index "%s" in block "%s". Valid indexes: [%s]',
                $index, $this->getName(), implode(', ', array_keys($this->properties))
            ));
        }
        return $this->properties[$index];
    }

    /**
     * Returns sizeof block
     * @return int
     */
    public function getLength()
    {
        return sizeof($this->properties);
    }

    /**
     * set value of child properties
     * @param mixed $value
     */
    public function setValue($value)
    {
        if ($value == null) {
            return;
        }

        // check value for single value
        if (array_keys($value) !== range(0, count($value) - 1)) {
            $value = array($value);
        }

        $this->properties = array();
        $len = count($value);
        for ($i = 0; $i < $len; $i++) {
            $item = $value[$i];
            $type = $this->initProperties($i, $item['type']);

            /** @var PropertyInterface $subProperty */
            foreach ($type->getChildProperties() as $subProperty) {
                if (!isset($item[$subProperty->getName()])) {
                    continue;
                }

                $subProperty->setValue($item[$subProperty->getName()]);
            }
        }
    }

    /**
     * get value of sub properties
     * @return array|mixed
     */
    public function getValue()
    {
        // if size of children smaller than minimum
        if (count($this->properties) < $this->getMinOccurs()) {
            for ($i = count($this->properties); $i < $this->getMinOccurs(); $i++) {
                $this->initProperties($i, $this->getDefaultTypeName());
            }
        }

        $data = array();
        foreach ($this->properties as $type) {
            $result = array('type' => $type->getName());
            foreach ($type->getChildProperties() as $property) {
                $result[$property->getName()] = $property->getValue();
            }
            $data[] = $result;
        }

        return $data;
    }

    /**
     * returns TRUE if property is a block
     * @return boolean
     */
    public function getIsBlock()
    {
        return true;
    }

    public function __clone()
    {
        $clone = new BlockProperty(
            $this->getName(),
            $this->getMetadata(),
            $this->getDefaultTypeName(),
            $this->getMandatory(),
            $this->getMultilingual(),
            $this->getMaxOccurs(),
            $this->getMinOccurs(),
            $this->getParams()
        );

        $clone->types = array();
        foreach ($this->types as $type) {
            $clone->addType(clone($type));
        }

        $clone->setValue($this->getValue());

        return $clone;
    }
}
