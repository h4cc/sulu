<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Component\Content;

use DateTime;
use Sulu\Component\Content\Exception\NoSuchPropertyException;
use Sulu\Component\Content\Section\SectionPropertyInterface;
use JMS\Serializer\Annotation\Type;
use JMS\Serializer\Annotation\Exclude;

/**
 * The Structure class represenets content structure and is the super type
 * for Page and Snippet classes.
 *
 * Structures are composed of properties which map to content types.
 */
abstract class Structure implements StructureInterface
{
    /**
     * indicates that the node is a content node
     */
    const NODE_TYPE_CONTENT = 1;

    /**
     * indicates that the node links to an internal resource
     */
    const NODE_TYPE_INTERNAL_LINK = 2;

    /**
     * indicates that the node links to an external resource
     */
    const NODE_TYPE_EXTERNAL_LINK = 4;

    /**
     * Structure type page
     */
    const TYPE_PAGE = 'page';

    /**
     * Structure type page
     */
    const TYPE_SNIPPET = 'snippet';

    /**
     * webspaceKey of node
     * @var string
     * @Type("string")
     */
    private $webspaceKey;

    /**
     * languageCode of node
     * @var string
     * @Type("string")
     */
    private $languageCode;

    /**
     * unique key of template
     * @var string
     * @Type("string")
     */
    private $key;

    /**
     * array of properties
     * @var array
     * @Type("array<string,Sulu\Component\Content\Property>")
     */
    private $properties = array();

    /**
     * has structure sub structures
     * @var bool
     * @Type("boolean")
     */
    private $hasChildren = false;

    /**
     * children of node
     * @var StructureInterface[]
     * @Exclude
     */
    private $children = null;

    /**
     * uuid of node in CR
     * @var string
     * @Type("string")
     */
    private $uuid;

    /**
     * user id of creator
     * @var int
     * @Type("integer")
     */
    private $creator;

    /**
     * user id of changer
     * @var int
     * @Type("integer")
     */
    private $changer;

    /**
     * datetime of creation
     * @var DateTime
     * @Type("DateTime")
     */
    private $created;

    /**
     * datetime of last changed
     * @var DateTime
     * @Type("DateTime")
     */
    private $changed;

    /**
     * first published
     * @var DateTime
     * @Type("DateTime")
     */
    private $published;

    /**
     * structure translation is valid
     * @var boolean
     * @Type("boolean")
     */
    private $hasTranslation;

    /**
     * @var StructureType
     * @Type("Sulu\Component\Content\Compat\StructureType")
     */
    private $type;

    /**
     * @var array
     * @Type("array")
     */
    private $tags = array();

    /**
     * type of node
     * @var integer
     * @Type("integer")
     */
    private $nodeType;

    /**
     * indicates internal structure
     * @var boolean
     * @Type("boolean")
     */
    private $internal;

    /**
     * content node is a shadow for another content
     * @var boolean
     * @Type("boolean")
     */
    private $isShadow;

    /**
     * when shadow is enabled, this node is a shadow for
     * this language
     * @var string
     * @Type("string")
     */
    private $shadowBaseLanguage = '';

    /**
     * the shadows which are activated on this node. Note this is
     * not stored in the phpcr node, it is determined by the content mapper.
     * @var array
     * @Type("array")
     */
    private $enabledShadowLanguages = array();

    /**
     * @var array
     * @Type("array")
     */
    private $concreteLanguages = array();

    /**
     * @var Metadata
     * @Type("Sulu\Component\Content\Metadata")
     */
    private $metaData;

    /**
     * @var StructureTag[]
     * @Type("array")
     */
    private $structureTags;

    /**
     * path of node
     * @var string
     * @Type("string")
     */
    private $path;

    /**
     * @param $key string
     */
    public function __construct($key, $metaData)
    {
        $this->key = $key;

        // default content node-type
        $this->nodeType = self::NODE_TYPE_CONTENT;
        $this->metaData = new Metadata($metaData);
        $this->published = null;
    }

    /**
     * adds a property to structure
     * @param PropertyInterface $property
     */
    protected function addChild(PropertyInterface $property)
    {
        if ($property instanceof SectionPropertyInterface) {
            foreach ($property->getChildProperties() as $childProperty) {
                $this->addPropertyTags($childProperty);
            }
        } else {
            $this->addPropertyTags($property);
        }

        $this->properties[$property->getName()] = $property;
    }

    /**
     * add tags of properties
     */
    protected function addPropertyTags(PropertyInterface $property)
    {
        foreach ($property->getTags() as $tag) {
            if (!array_key_exists($tag->getName(), $this->tags)) {
                $this->tags[$tag->getName()] = array(
                    'tag' => $tag,
                    'properties' => array($tag->getPriority() => $property),
                    'highest' => $property,
                    'lowest' => $property
                );
            } else {
                $this->tags[$tag->getName()]['properties'][$tag->getPriority()] = $property;

                // replace highest priority property
                $highestProperty = $this->tags[$tag->getName()]['highest'];
                if ($highestProperty->getTag($tag->getName())->getPriority() < $tag->getPriority()) {
                    $this->tags[$tag->getName()]['highest'] = $property;
                }

                // replace lowest priority property
                $lowestProperty = $this->tags[$tag->getName()]['lowest'];
                if ($lowestProperty->getTag($tag->getName())->getPriority() > $tag->getPriority()) {
                    $this->tags[$tag->getName()]['lowest'] = $property;
                }
            }
        }
    }

    /**
     * @param string $language
     */
    public function setLanguageCode($language)
    {
        $this->languageCode = $language;
    }

    /**
     * returns language of node
     * @return string
     */
    public function getLanguageCode()
    {
        return $this->languageCode;
    }

    /**
     * @param string $webspace
     */
    public function setWebspaceKey($webspace)
    {
        $this->webspaceKey = $webspace;
    }

    /**
     * returns webspace of node
     * @return string
     */
    public function getWebspaceKey()
    {
        return $this->webspaceKey;
    }

    /**
     * key of template definition
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * returns uuid of node
     * @return string
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * sets uuid of node
     * @param $uuid
     */
    public function setUuid($uuid)
    {
        $this->uuid = $uuid;
    }

    /**
     * returns id of creator
     * @return int
     */
    public function getCreator()
    {
        return $this->creator;
    }

    /**
     * sets user id of creator
     * @param $userId int id of creator
     */
    public function setCreator($userId)
    {
        $this->creator = $userId;
    }

    /**
     * returns user id of changer
     * @return int
     */
    public function getChanger()
    {
        return $this->changer;
    }

    /**
     * sets user id of changer
     * @param $userId int id of changer
     */
    public function setChanger($userId)
    {
        $this->changer = $userId;
    }

    /**
     * return created datetime
     * @return DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * sets created datetime
     * @param DateTime $created
     * @return \DateTime
     */
    public function setCreated(DateTime $created)
    {
        return $this->created = $created;
    }

    /**
     * returns changed DateTime
     * @return DateTime
     */
    public function getChanged()
    {
        return $this->changed;
    }

    /**
     * sets changed datetime
     * @param \DateTime $changed
     */
    public function setChanged(DateTime $changed)
    {
        $this->changed = $changed;
    }

    /**
     * returns a property instance with given name
     * @param $name string name of property
     * @return PropertyInterface
     * @throws NoSuchPropertyException
     */
    public function getProperty($name)
    {
        $result = $this->findProperty($name);

        if ($result !== null) {
            return $result;
        } elseif (isset($this->properties[$name])) {
            return $this->properties[$name];
        } else {
            throw new NoSuchPropertyException($name);
        }
    }

    /**
     * returns a property instance with given tag name
     * @param string $tagName
     * @param boolean $highest
     * @throws \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     * @return PropertyInterface
     */
    public function getPropertyByTagName($tagName, $highest = true)
    {
        if (array_key_exists($tagName, $this->tags)) {
            return $this->tags[$tagName][$highest === true ? 'highest' : 'lowest'];
        } else {
            throw new NoSuchPropertyException($tagName);
        }
    }

    /**
     * returns properties with given tag name sorted by priority
     * @param string $tagName
     * @throws \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     * @return PropertyInterface
     */
    public function getPropertiesByTagName($tagName)
    {
        if (array_key_exists($tagName, $this->tags)) {
            return $this->tags[$tagName]['properties'];
        } else {
            throw new NoSuchPropertyException($tagName);
        }
    }

    /**
     * return value of property with given name
     * @param $name string name of property
     * @return mixed
     */
    public function getPropertyValue($name)
    {
        return $this->getProperty($name)->getValue();
    }

    /**
     * returns value of property with given tag name
     * @param string $tagName
     * @return mixed
     */
    public function getPropertyValueByTagName($tagName)
    {
        return $this->getPropertyByTagName($tagName, true)->getValue();
    }

    /**
     * checks if a property exists
     * @param string $name
     * @return boolean
     */
    public function hasProperty($name)
    {
        return $this->findProperty($name) !== null;
    }

    /**
     * find property in flatten properties
     * @param string $name
     * @return null|PropertyInterface
     */
    private function findProperty($name)
    {
        foreach ($this->getProperties(true) as $property) {
            if ($property->getName() === $name) {
                return $property;
            }
        }

        return null;
    }

    /**
     * @param boolean $hasChildren
     */
    public function setHasChildren($hasChildren)
    {
        $this->hasChildren = $hasChildren;
    }

    /**
     * @return boolean
     */
    public function getHasChildren()
    {
        return $this->hasChildren;
    }

    /**
     * @param StructureInterface[] $children
     */
    public function setChildren($children)
    {
        $this->children = $children;
    }

    /**
     * @return null|StructureInterface[]
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * returns true if state of site is "published"
     * @return boolean
     */
    public function getPublishedState()
    {
        return ($this->nodeState === StructureInterface::STATE_PUBLISHED);
    }

    /**
     * @param \DateTime $published
     */
    public function setPublished($published)
    {
        $this->published = $published;
    }

    /**
     * returns first published date
     * @return \DateTime
     */
    public function getPublished()
    {
        return $this->published;
    }

    /**
     * @param boolean $hasTranslation
     */
    public function setHasTranslation($hasTranslation)
    {
        $this->hasTranslation = $hasTranslation;
    }

    /**
     * set if this structure should act like a shadow
     * @return boolean
     */
    public function getIsShadow()
    {
        return $this->isShadow;
    }

    /**
     * set if this node should act like a shadow
     * @param boolean
     */
    public function setIsShadow($isShadow)
    {
        $this->isShadow = $isShadow;
    }

    /**
     * return the shadow base language
     * @return string
     */
    public function getShadowBaseLanguage()
    {
        return $this->shadowBaseLanguage;
    }

    /**
     * set the shadow base language
     * @param string $shadowBaseLanguage
     */
    public function setShadowBaseLanguage($shadowBaseLanguage)
    {
        $this->shadowBaseLanguage = $shadowBaseLanguage;
    }

    /**
     * return true if structure translation is valid
     * @return boolean
     */
    public function getHasTranslation()
    {
        return $this->hasTranslation;
    }

    /**
     * returns an array of properties
     * @param bool $flatten
     * @return PropertyInterface[]
     */
    public function getProperties($flatten = false)
    {
        if ($flatten === false) {
            return $this->properties;
        } else {
            $result = array();
            foreach ($this->properties as $property) {
                if ($property instanceof SectionPropertyInterface) {
                    $result = array_merge($result, $property->getChildProperties());
                } else {

                    $result[] = $property;
                }
            }

            return $result;
        }
    }

    /**
     * returns all property names
     * @return array
     */
    public function getPropertyNames()
    {
        return array_keys($this->properties);
    }

    /**
     * @param \Sulu\Component\Content\Compat\StructureType $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return \Sulu\Component\Content\Compat\StructureType
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return int
     */
    public function getNodeType()
    {
        return $this->nodeType;
    }

    /**
     * @param int $nodeType
     */
    public function setNodeType($nodeType)
    {
        $this->nodeType = $nodeType;
    }

    /**
     * @return boolean
     */
    public function getInternal()
    {
        return $this->internal;
    }

    /**
     * @param boolean $internal
     */
    public function setInternal($internal)
    {
        $this->internal = $internal;
    }

    /**
     * {@inheritdoc}
     */
    public function getNodeName()
    {
        if (
            $this->getNodeType() === Structure::NODE_TYPE_INTERNAL_LINK &&
            $this->getInternalLinkContent() !== null &&
            $this->getInternalLinkContent()->hasProperty('title')
        ) {
            return $this->internalLinkContent->getPropertyValue('title');
        } elseif ($this->hasProperty('title')) {
            return $this->getPropertyValue('title');
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function hasTag($tag)
    {
        return array_key_exists($tag, $this->tags);
    }

    /**
     * {@inheritdoc}
     */
    public function getLocalizedTitle($languageCode)
    {
        $default = ucfirst($this->key);
        if ($this->metaData) {
            return $this->metaData->get('title', $languageCode, $default);
        } else {
            return $default;
        }
    }

    /**
     * magic getter
     * @param $property string name of property
     * @return mixed
     * @throws NoSuchPropertyException
     */
    public function __get($property)
    {
        if (method_exists($this, 'get' . ucfirst($property))) {
            return $this->{'get' . ucfirst($property)}();
        } else {
            return $this->getProperty($property)->getValue();
        }
    }

    /**
     * magic setter
     * @param $property string name of property
     * @param $value mixed value
     * @return mixed
     * @throws NoSuchPropertyException
     */
    public function __set($property, $value)
    {
        if (isset($this->properties[$property])) {
            return $this->getProperty($property)->setValue($value);
        } else {
            throw new NoSuchPropertyException($property);
        }
    }

    /**
     * magic isset
     * @param $property
     * @return bool
     */
    public function __isset($property)
    {
        if ($this->findProperty($property) !== null) {
            return true;
        } else {
            return isset($this->$property);
        }
    }

    /**
     * returns an array of property value pairs
     * @param bool $complete True if result should be representation of full node
     * @return array
     */
    public function toArray($complete = true)
    {
        if ($complete) {
            $result = array(
                'id' => $this->uuid,
                'nodeType' => $this->nodeType,
                'internal' => $this->internal,
                'enabledShadowLanguages' => $this->getEnabledShadowLanguages(),
                'concreteLanguages' => $this->getConcreteLanguages(),
                'shadowOn' => $this->getIsShadow(),
                'shadowBaseLanguage' => $this->getShadowBaseLanguage() ? : false,
                'template' => $this->getKey(),
                'hasSub' => $this->hasChildren,
                'creator' => $this->creator,
                'changer' => $this->changer,
                'created' => $this->created,
                'changed' => $this->changed
            );

            if ($this->type !== null) {
                $result['type'] = $this->getType()->toArray();
            }

            if ($this->nodeType === self::NODE_TYPE_INTERNAL_LINK) {
                $result['linked'] = 'internal';
            } elseif ($this->nodeType === self::NODE_TYPE_EXTERNAL_LINK) {
                $result['linked'] = 'external';
            }

            $this->appendProperties($this->getProperties(), $result);

            return $result;
        } else {
            $result = array(
                'id' => $this->uuid,
                'path' => $this->path,
                'nodeType' => $this->nodeType,
                'internal' => $this->internal,
                'concreteLanguages' => $this->getConcreteLanguages(),
                'hasSub' => $this->hasChildren,
                'title' => $this->getProperty('title')->toArray()
            );

            if ($this->type !== null) {
                $result['type'] = $this->getType()->toArray();
            }

            if ($this->nodeType === self::NODE_TYPE_INTERNAL_LINK) {
                $result['linked'] = 'internal';
            } elseif ($this->nodeType === self::NODE_TYPE_EXTERNAL_LINK) {
                $result['linked'] = 'external';
            }

            return $result;
        }
    }

    private function appendProperties($properties, &$array)
    {
        /** @var PropertyInterface $property */
        foreach ($properties as $property) {
            if ($property instanceof SectionPropertyInterface) {
                $this->appendProperties($property->getChildProperties(), $array);
            } else {
                $array[$property->getName()] = $property->toArray();
            }
        }
    }

    /**
     * (PHP 5 &gt;= 5.4.0)<br/>
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * return available shadow languages on this structure
     * (determined at runtime)
     * @return array
     */
    public function getEnabledShadowLanguages()
    {
        return $this->enabledShadowLanguages;
    }

    /**
     * set the available enabled shadow languages
     * @param array
     */
    public function setEnabledShadowLanguages($enabledShadowLanguages)
    {
        $this->enabledShadowLanguages = $enabledShadowLanguages;
    }

    /**
     * Return the available concrete languages (i.e.
     * the languages which are set and are not shadows)
     *
     * @return array
     */
    public function getConcreteLanguages()
    {
        return $this->concreteLanguages;
    }

    /**
     * Set the available concrete languages (note this should
     * only be done internally)
     *
     * @param array $concreteLanguages
     */
    public function setConcreteLanguages($concreteLanguages)
    {
        $this->concreteLanguages = $concreteLanguages;
    }

    /**
     * Add a tag to this structure
     */
    public function addStructureTag(StructureTag $structureTag)
    {
        $this->structureTags[$structureTag->getName()] = $structureTag;
    }

    /**
     * Return true if this structure has the given tag
     *
     * @return boolean
     */
    public function hasStructureTag($name)
    {
        return isset($this->structureTags[$name]);
    }

    /**
     * Return the tag with the given name
     *
     * @param string $name
     * @throws \InvalidArgumentException
     */
    public function getStructureTag($name)
    {
        if (!isset($this->structureTags[$name])) {
            throw new \InvalidArgumentException(sprintf('Trying to get undefined structure StructureTag "%s"', $name));
        }

        return $this->structureTags[$name];
    }

    public function getNodeState()
    {
        return Structure::STATE_PUBLISHED;
    }

    /**
     * returns absolute path of node
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param string $path
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * {@inheritdoc}
     */
    public function copyFrom(StructureInterface $structure)
    {
        $this->setWebspaceKey($structure->getWebspaceKey());
        $this->setLanguageCode($structure->getLanguageCode());
        $this->setUuid($structure->getUuid());

        $this->setChanged($structure->getChanged());
        $this->setChanger($structure->getChanger());
        $this->setCreated($structure->getCreated());
        $this->setCreator($structure->getCreator());

        $this->setPublished($structure->getPublished());
        $this->setPath($structure->getPath());
        $this->setNodeType($structure->getNodeType());
        $this->setHasTranslation($structure->getHasTranslation());
    }
}
