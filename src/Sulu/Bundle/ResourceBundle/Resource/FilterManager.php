<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ResourceBundle\Resource;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\ResourceBundle\Entity\Condition as ConditionEntity;
use Sulu\Bundle\ResourceBundle\Entity\ConditionGroup as ConditionGroupEntity;
use Sulu\Bundle\ResourceBundle\Api\Filter;
use Sulu\Bundle\ResourceBundle\Entity\ConditionRepositoryInterface;
use Sulu\Bundle\ResourceBundle\Entity\Filter as FilterEntity;
use Sulu\Bundle\ResourceBundle\Entity\FilterRepositoryInterface;
use Sulu\Bundle\ResourceBundle\Resource\Exception\ConditionGroupMismatchException;
use Sulu\Bundle\ResourceBundle\Resource\Exception\FilterContextNotFoundException;
use Sulu\Bundle\ResourceBundle\Resource\Exception\FilterDependencyNotFoundException;
use Sulu\Bundle\ResourceBundle\Resource\Exception\FilterNotFoundException;
use Sulu\Bundle\ResourceBundle\Resource\Exception\MissingConditionAttributeException;
use Sulu\Bundle\ResourceBundle\Resource\Exception\MissingFilterAttributeException;
use Sulu\Component\Persistence\RelationTrait;
use Sulu\Component\Rest\Exception\EntityIdAlreadySetException;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineFieldDescriptor;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineJoinDescriptor;
use Sulu\Component\Security\Authentication\UserRepositoryInterface;

/**
 * Manager responsible for filters
 * Class FilterManager
 * @package Sulu\Bundle\ResourceBundle\Filter
 */
class FilterManager implements FilterManagerInterface
{
    use RelationTrait;

    protected static $filterEntityName = 'SuluResourceBundle:Filter';
    protected static $userEntityName = 'SuluSecurityBundle:User';
    protected static $conditionGroupEntityName = 'SuluResourceBundle:ConditionGroup';
    protected static $conditionEntityName = 'SuluResourceBundle:Condition';
    protected static $filterTranslationEntityName = 'SuluResourceBundle:FilterTranslation';

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var FilterRepositoryInterface
     */
    protected $filterRepository;

    /**
     * @var UserRepositoryInterface
     */
    protected $userRepository;

    /**
     * @var ConditionRepositoryInterface
     */
    protected $conditionRepository;

    /**
     * @var array
     */
    protected $aliasConfiguration;

    public function __construct(
        EntityManagerInterface $em,
        FilterRepositoryInterface $filterRepo,
        UserRepositoryInterface $userRepository,
        ConditionRepositoryInterface $conditionRepository,
        array $aliasConfiguration
    ) {
        $this->em = $em;
        $this->filterRepository = $filterRepo;
        $this->userRepository = $userRepository;
        $this->conditionRepository = $conditionRepository;
        $this->aliasConfiguration = $aliasConfiguration;
    }

    /**
     * {@inheritDoc}
     */
    public function getFieldDescriptors($locale)
    {
        $fieldDescriptors = array();
        $fieldDescriptors['id'] = new DoctrineFieldDescriptor(
            'id',
            'id',
            self::$filterEntityName,
            'public.id',
            array(),
            true
        );
        $fieldDescriptors['name'] = new DoctrineFieldDescriptor(
            'name',
            'name',
            self::$filterTranslationEntityName,
            'resource.filter.name',
            array(
                self::$filterTranslationEntityName => new DoctrineJoinDescriptor(
                    self::$filterTranslationEntityName,
                    self::$filterEntityName . '.translations',
                    self::$filterTranslationEntityName . '.locale = \'' . $locale . '\''
                ),
            )
        );

        return $fieldDescriptors;
    }

    /**
     * {@inheritDoc}
     */
    public function getListFieldDescriptors($locale){
        $fieldDescriptors = $this->getFieldDescriptors($locale);

        $fieldDescriptors['context'] = new DoctrineFieldDescriptor(
            'context',
            'context',
            self::$filterEntityName,
            'public.context',
            array(),
            true
        );

        $fieldDescriptors['user'] = new DoctrineFieldDescriptor(
            'id',
            'user',
            self::$userEntityName,
            'public.user',
            array(
                self::$userEntityName => new DoctrineJoinDescriptor(
                    self::$userEntityName,
                    self::$filterEntityName . '.user'
                ),
            ),
            true
        );

        return $fieldDescriptors;
    }

    /**
     * {@inheritDoc}
     */
    public function findByIdAndLocale($id, $locale)
    {
        $filter = $this->filterRepository->findByIdAndLocale($id, $locale);
        if ($filter) {
            return new Filter($filter, $locale);
        } else {
            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findAllByLocale($locale)
    {
        $filters = $this->filterRepository->findAllByLocale($locale);
        array_walk(
            $filters,
            function (&$filter) use ($locale) {
                $filter = new Filter($filter, $locale);
            }
        );

        return $filters;
    }

    /**
     * {@inheritDoc}
     */
    public function delete($id)
    {
        $filter = $this->filterRepository->findById($id);
        if (!$filter) {
            throw new FilterNotFoundException($id);
        }
        $this->em->remove($filter);
        $this->em->flush();
    }

    /**
     * {@inheritDoc}
     */
    public function save(array $data, $locale, $userId, $id = null)
    {
        $user = $this->userRepository->findUserById($userId);

        // SECURITY: Only the user which is referenced by the filter should be allowed to
        // to change the filter - or the administrator

        if ($id) {
            $filter = $this->filterRepository->findByIdAndLocale($id, $locale);
            if (!$filter) {
                throw new FilterNotFoundException($id);
            }
            $filter = new Filter($filter, $locale);
        } else {
            $this->checkData($data, true);
            $filter = new Filter(new FilterEntity(), $locale);
            $filter->setCreated(new \DateTime());
            $filter->setCreator($user);
            $this->em->persist($filter->getEntity());
        }

        // set direct properties and translation
        $filter->setChanged(new \DateTime());
        $filter->setChanger($user);
        $filter->setName($this->getProperty($data, 'name', $filter->getName()));

        if(array_key_exists('context', $data)) {
            if ($this->getClassMappingForKey($data['context'])) {
                $filter->setContext($data['context']);
            } else {
                throw new FilterContextNotFoundException(static::$filterEntityName, $data['context']);
            }
        }

        $filter->setConjunction($this->getProperty($data, 'conjunction', $filter->getConjunction()));
        $filter->setChanger($user);
        $filter->setChanged(new \DateTime());

        // set user for filter
        $filter->setUser($user);

        // update condition groups and conditions
        if (isset($data['conditionGroups'])) {
            $get = function (ConditionGroupEntity $conditionGroup) {
                return $conditionGroup->getId();
            };

            $add = function ($conditionGroupData) use ($filter) {
                return $this->addConditionGroup($filter, $conditionGroupData);
            };

            $delete = function (ConditionGroupEntity $conditionGroup) use ($filter) {
                $this->em->remove($conditionGroup);

                return true;
            };

            $update = function (ConditionGroupEntity $conditionGroup, $matchedEntry) {
                return $this->updateConditionGroup($conditionGroup, $matchedEntry);
            };

            $this->processSubEntities(
                $filter->getEntity()->getConditionGroups(),
                $data['conditionGroups'],
                $get,
                $add,
                $update,
                $delete
            );
        }

        $this->em->flush();

        return $filter;
    }

    /**
     * Updates the given condition group with the values from the given array
     *
     * @param ConditionGroupEntity $conditionGroup
     * @param array $matchedEntry
     * @return bool
     * @throws ConditionGroupMismatchException
     * @throws FilterDependencyNotFoundException
     */
    protected function updateConditionGroup(ConditionGroupEntity $conditionGroup, $matchedEntry)
    {
        if (array_key_exists('id', $matchedEntry) && isset($matchedEntry['conditions'])) {
            foreach ($matchedEntry['conditions'] as $conditionData) {
                if (array_key_exists('id', $conditionData)) {
                    /** @var ConditionEntity $conditionEntity */
                    $conditionEntity = $this->conditionRepository->findById($conditionData['id']);

                    // check if condition exists at all
                    if (!$conditionEntity) {
                        throw new FilterDependencyNotFoundException(
                            self::$conditionEntityName,
                            $conditionData['id']
                        );
                    }

                    // check if conditions is related with condition group
                    if ($conditionEntity->getConditionGroup()->getId() !== $conditionGroup->getId()) {
                        throw new ConditionGroupMismatchException(
                            $matchedEntry['id']
                        );
                    }
                } else {
                    $conditionEntity = new ConditionEntity();
                    $conditionEntity->setConditionGroup($conditionGroup);
                    $this->em->persist($conditionEntity);
                }

                $conditionEntity->setField($this->getProperty($conditionData, 'field', $conditionEntity->getField()));
                $conditionEntity->setOperator(
                    $this->getProperty($conditionData, 'operator', $conditionEntity->getOperator())
                );
                $conditionEntity->setValue($this->getProperty($conditionData, 'value', $conditionEntity->getValue()));
                $conditionEntity->setType($this->getProperty($conditionData, 'type', $conditionEntity->getType()));
                $conditionGroup->addCondition($conditionEntity);
            }
        }

        return true;
    }

    /**
     * Adds a condition group to the given filter
     *
     * @param Filter $filter The filter to add the condition group to
     * @param array $conditionGroupData The array containing the data for the additional condition group
     * @return bool
     * @throws EntityIdAlreadySetException
     * @throws FilterDependencyNotFoundException
     */
    protected function addConditionGroup(Filter $filter, $conditionGroupData)
    {
        if (array_key_exists('id', $conditionGroupData)) {
            throw new EntityIdAlreadySetException(self::$conditionGroupEntityName, $conditionGroupData['id']);
        } elseif (array_key_exists('conditions', $conditionGroupData)) {
            $conditionGroup = new ConditionGroupEntity();
            $conditionGroup->setFilter($filter->getEntity());

            foreach ($conditionGroupData['conditions'] as $conditionData) {
                if (array_key_exists('id', $conditionData)) {
                    throw new EntityIdAlreadySetException(self::$conditionEntityName, $conditionData['id']);
                } elseif ($this->isValidConditionData($conditionData)) {
                    $condition = new ConditionEntity();
                    $condition->setValue($conditionData['value']);
                    $condition->setType($conditionData['type']);
                    $condition->setOperator($conditionData['operator']);
                    $condition->setField($conditionData['field']);
                    $condition->setConditionGroup($conditionGroup);
                    $conditionGroup->addCondition($condition);
                    $conditionGroup->setFilter($filter->getEntity());
                    $this->em->persist($condition);
                }
            }

            $filter->getEntity()->addConditionGroup($conditionGroup);
            $this->em->persist($conditionGroup);
        }

        return true;
    }

    /**
     * Returns the entry from the data with the given key, or the given default value, if the key does not exist
     *
     * @param array $data
     * @param string $key
     * @param string $default
     * @return mixed
     */
    protected function getProperty(array $data, $key, $default = null)
    {
        return array_key_exists($key, $data) ? $data[$key] : $default;
    }

    /**
     * Checks if the given data is correct
     *
     * @param array $data The data to check
     * @param boolean $create Defines if check is for new or already existing data
     */
    protected function checkData($data, $create)
    {
        $this->checkDataSet($data, 'name', $create);
        $this->checkDataSet($data, 'conjunction', $create);
        $this->checkDataSet($data, 'context', $create);
    }

    /**
     * Checks if data for the given key is set correctly
     *
     * @param array $data The array with the data
     * @param string $key The array key to check
     * @param bool $create Defines if the is for new or already existing data
     * @return bool
     * @throws Exception\MissingFilterAttributeException
     */
    protected function checkDataSet(array $data, $key, $create)
    {
        $keyExists = array_key_exists($key, $data);
        if (($create && !($keyExists && $data[$key] !== null)) || (!$keyExists || $data[$key] === null)) {
            throw new MissingFilterAttributeException($key);
        }

        return $keyExists;
    }

    /**
     * Checks if the given data is correct for a condition
     *
     * @param array $data The data to check
     * @return bool
     * @throws MissingConditionAttributeException
     */
    protected function isValidConditionData($data)
    {
        if (!array_key_exists('field', $data)) {
            throw new MissingConditionAttributeException('field');
        }
        if (!array_key_exists('operator', $data)) {
            throw new MissingConditionAttributeException('operator');
        }
        if (!array_key_exists('type', $data)) {
            throw new MissingConditionAttributeException('type');
        }
        if (!array_key_exists('value', $data)) {
            throw new MissingConditionAttributeException('value');
        }

        return true;
    }

    /**
     * Deletes multiple filters at once
     *
     * @param array $ids
     */
    public function batchDelete($ids)
    {
        $this->filterRepository->deleteByIds($ids);
    }

    /**
     * Returns the configured class for a key
     * @param string $key
     * @return string|null
     */
    public function getClassMappingForKey($key)
    {
        if($this->aliasConfiguration && array_key_exists($key, $this->aliasConfiguration)){
            return $this->aliasConfiguration[$key]['class'];
        }

        return null;
    }
}