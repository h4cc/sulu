<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ContentBundle\Document;

use Sulu\Component\DocumentManager\Behavior\NodeNameBehavior;
use Sulu\Component\DocumentManager\Behavior\TimestampBehavior;
use Sulu\Component\DocumentManager\Behavior\BlameBehavior;
use Sulu\Component\DocumentManager\Behavior\ParentBehavior;
use Sulu\Component\DocumentManager\Behavior\UuidBehavior;
use Sulu\Component\Content\Document\Behavior\ResourceSegmentBehavior;
use Sulu\Component\Content\Document\Behavior\NavigationContextBehavior;
use Sulu\Component\Content\Document\Behavior\RedirectTypeBehavior;
use Sulu\Component\Content\Document\Behavior\WorkflowStageBehavior;
use Sulu\Component\Content\Document\Behavior\ShadowLocaleBehavior;
use Sulu\Component\Content\Document\WorkflowStage;
use Sulu\Component\Content\Document\RedirectType;
use Sulu\Component\Content\Document\Behavior\LocalizedContentBehavior;
use Sulu\Component\Content\Document\Property\PropertyContainer;
use Sulu\Component\DocumentManager\Behavior\ChildrenBehavior;
use Sulu\Component\DocumentManager\Behavior\PathBehavior;
use Sulu\Component\Content\Document\Behavior\ExtensionBehavior;
use Sulu\Component\Content\Document\Behavior\OrderBehavior;
use Sulu\Component\Content\Document\Behavior\WebspaceBehavior;

class BasePageDocument implements
    NodeNameBehavior,
    TimestampBehavior,
    BlameBehavior,
    ParentBehavior,
    LocalizedContentBehavior,
    ResourceSegmentBehavior,
    NavigationContextBehavior,
    RedirectTypeBehavior,
    WorkflowStageBehavior,
    ShadowLocaleBehavior,
    UuidBehavior,
    ChildrenBehavior,
    PathBehavior,
    ExtensionBehavior,
    OrderBehavior,
    WebspaceBehavior
{
    protected $nodeName;
    protected $created;
    protected $changed;
    protected $creator;
    protected $changer;
    protected $parent;
    protected $title;
    protected $resourceSegment;
    protected $navigationContexts = array();
    protected $redirectType;
    protected $redirectTarget;
    protected $redirectExternal;
    protected $workflowStage;
    protected $published;
    protected $shadowLocaleEnabled = false;
    protected $shadowLocale;
    protected $uuid;
    protected $structureType;
    protected $content;
    protected $locale;
    protected $children;
    protected $path;
    protected $extensions = array();

    public function __construct()
    {
        $this->workflowStage = WorkflowStage::TEST;
        $this->redirectType = RedirectType::NONE;
        $this->content = new PropertyContainer();
        $this->children = new \ArrayIterator();
    }

    /**
     * {@inheritDoc}
     */
    public function getNodeName() 
    {
        return $this->nodeName;
    }

    /**
     * {@inheritDoc}
     */
    public function getTitle() 
    {
        return $this->title;
    }

    /**
     * Set the title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * {@inheritDoc}
     */
    public function getCreated() 
    {
        return $this->created;
    }

    /**
     * {@inheritDoc}
     */
    public function getChanged() 
    {
        return $this->changed;
    }

    /**
     * {@inheritDoc}
     */
    public function getCreator() 
    {
        return $this->creator;
    }

    /**
     * {@inheritDoc}
     */
    public function getChanger() 
    {
        return $this->changer;
    }

    /**
     * {@inheritDoc}
     */
    public function getParent() 
    {
        return $this->parent;
    }

    /**
     * {@inheritDoc}
     */
    public function setParent($parent)
    {
        $this->parent = $parent;
    }

    /**
     * {@inheritDoc}
     */
    public function getResourceSegment() 
    {
        return $this->resourceSegment;
    }
    
    /**
     * {@inheritDoc}
     */
    public function setResourceSegment($resourceSegment)
    {
        $this->resourceSegment = $resourceSegment;
    }

    /**
     * {@inheritDoc}
     */
    public function getNavigationContexts() 
    {
        return $this->navigationContexts;
    }
    
    /**
     * {@inheritDoc}
     */
    public function setNavigationContexts(array $navigationContexts = array())
    {
        $this->navigationContexts = $navigationContexts;
    }

    /**
     * {@inheritDoc}
     */
    public function getRedirectType() 
    {
        return $this->redirectType;
    }
    
    /**
     * {@inheritDoc}
     */
    public function setRedirectType($redirectType)
    {
        $this->redirectType = $redirectType;
    }

    /**
     * {@inheritDoc}
     */
    public function getRedirectTarget() 
    {
        return $this->redirectTarget;
    }
    
    /**
     * {@inheritDoc}
     */
    public function setRedirectTarget($redirectTarget)
    {
        $this->redirectTarget = $redirectTarget;
    }

    /**
     * {@inheritDoc}
     */
    public function getRedirectExternal() 
    {
        return $this->redirectExternal;
    }
    
    /**
     * {@inheritDoc}
     */
    public function setRedirectExternal($redirectExternal)
    {
        $this->redirectExternal = $redirectExternal;
    }

    /**
     * {@inheritDoc}
     */
    public function getWorkflowStage() 
    {
        return $this->workflowStage;
    }
    
    /**
     * {@inheritDoc}
     */
    public function setWorkflowStage($workflowStage)
    {
        $this->workflowStage = $workflowStage;
    }

    /**
     * {@inheritDoc}
     */
    public function getPublished()
    {
        return $this->published;
    }

    /**
     * {@inheritDoc}
     */
    public function getShadowLocale() 
    {
        return $this->shadowLocale;
    }
    
    /**
     * {@inheritDoc}
     */
    public function setShadowLocale($shadowLocale)
    {
        $this->shadowLocale = $shadowLocale;
    }

    /**
     * {@inheritDoc}
     */
    public function isShadowLocaleEnabled() 
    {
        return $this->shadowLocaleEnabled;
    }
    
    /**
     * {@inheritDoc}
     */
    public function setShadowLocaleEnabled($shadowLocaleEnabled)
    {
        $this->shadowLocaleEnabled = $shadowLocaleEnabled;
    }

    /**
     * {@inheritDoc}
     */
    public function getUuid() 
    {
        return $this->uuid;
    }

    /**
     * {@inheritDoc}
     */
    public function getStructureType() 
    {
        return $this->structureType;
    }

    /**
     * {@inheritDoc}
     */
    public function getContent()
    {
        return $this->content;
    }
    
    /**
     * {@inheritDoc}
     */
    public function setStructureType($structureType)
    {
        $this->structureType = $structureType;
    }


    /**
     * {@inheritDoc}
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * {@inheritDoc}
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * {@inheritDoc}
     */
    public function getPath() 
    {
        return $this->path;
    }

    /**
     * {@inheritDoc}
     */
    public function getExtensionsData() 
    {
        return $this->extensions;
    }
    
    /**
     * {@inheritDoc}
     */
    public function setExtensionsData($extensions)
    {
        $this->extensions = $extensions;
    }

    /**
     * {@inheritDoc}
     */
    public function setExtension($name, $data)
    {
        $this->extensions[$name] = $data;
    }
}
