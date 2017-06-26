<?php
namespace LuceneSearchBundle\Task\Crawler\Filter\PostFetch;

use VDB\Spider\Filter\PostFetchFilterInterface;
use VDB\Spider\Resource;

class MaxContentSizeFilter implements PostFetchFilterInterface
{
    /**
     * @var float|int
     */
    protected $maxFileSize = 0;

    /**
     * MaxContentSizeFilter constructor.
     *
     * @param int $maxFileSize
     */
    public function __construct($maxFileSize = 0)
    {
        $this->maxFileSize = (float) $maxFileSize;
    }

    /**
     * @param Resource $resource
     *
     * @return bool
     */
    public function match(Resource $resource)
    {
        $size = $resource->getResponse()->getBody()->getSize();
        $sizeMb = $size / 1024 / 1024;

        if ($this->maxFileSize === 0 || $sizeMb <= $this->maxFileSize) {
            return FALSE;
        }

        return TRUE;
    }
}
