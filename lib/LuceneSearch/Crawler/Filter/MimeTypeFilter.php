<?php
namespace LuceneSearch\Crawler\Filter;

use VDB\Spider\Filter\PostFetchFilterInterface;
use VDB\Spider\Resource;

class MimeTypeFilter implements PostFetchFilterInterface
{
    /**
     * @var array
     */
    protected $allowedMimeType = [];

    /**
     * MimeTypeFilter constructor.
     *
     * @param $allowedMimeType
     */
    public function __construct($allowedMimeType)
    {
        $this->allowedMimeType = $allowedMimeType;
    }

    /**
     * @param Resource $resource
     *
     * @return bool
     */
    public function match(Resource $resource)
    {
        $hasContentType = count((array_intersect(array_map(function ($allowed) use ($resource) {
                return $resource->getResponse()->isContentType($allowed);
            }, $this->allowedMimeType), [TRUE]))) > 0;

        return !$hasContentType;
    }
}