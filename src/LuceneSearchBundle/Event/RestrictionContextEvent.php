<?php

namespace LuceneSearchBundle\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Use this event to check current frontend restriction context.
 * Class RestrictionContextEvent
 *
 * @package LuceneSearchBundle\Event
 */
class RestrictionContextEvent extends Event
{
    /**
     * @var array
     */
    private $restrictionGroups = [];

    /**
     * @param $restrictionGroups array
     */
    public function setAllowedRestrictionGroups($restrictionGroups)
    {
        $this->restrictionGroups = $restrictionGroups;
    }

    /**
     * @return array|null
     */
    public function getAllowedRestrictionGroups()
    {
        return $this->restrictionGroups;
    }
}