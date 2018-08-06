<?php

namespace LuceneSearchBundle\Event;

use Pimcore\Model\Asset;
use Symfony\Component\EventDispatcher\Event;
use VDB\Spider\Resource;

class AssetResourceRestrictionEvent extends Event
{
    /**
     * @var Resource
     */
    private $resource;

    /**
     * @var Asset
     */
    private $asset;

    /**
     * @var array
     */
    private $restrictions = [];

    /**
     * UserEvent constructor.
     *
     * @param Resource $resource
     */
    public function __construct(Resource $resource = null)
    {
        $this->resource = $resource;
    }

    /**
     * @return Resource
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * @param $restrictions array|null
     */
    public function setRestrictions($restrictions)
    {
        $this->restrictions = $restrictions;
    }

    /**
     * @return array|null
     */
    public function getRestrictions()
    {
        return $this->restrictions;
    }

    /**
     * @param $asset Asset
     */
    public function setAsset(Asset $asset)
    {
        $this->asset = $asset;
    }

    /**
     * @return Asset
     */
    public function getAsset()
    {
        return $this->asset;
    }
}