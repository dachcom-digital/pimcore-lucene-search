<?php
namespace LuceneSearch\Crawler\Filter;

use VDB\Spider\Filter\PreFetchFilterInterface;
use VDB\Uri\UriInterface;

class PimcoreUriFilter implements PreFetchFilterInterface
{
    private $invalidUrls;

    public function __construct( $invalidUrls)
    {
        $this->invalidUrls = $invalidUrls;


    }

    public function match(UriInterface $uri)
    {
        return null !== $uri->getQuery();
    }
}
