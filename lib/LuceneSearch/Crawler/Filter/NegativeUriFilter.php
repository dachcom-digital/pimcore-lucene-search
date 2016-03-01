<?php
namespace LuceneSearch\Crawler\Filter;

use VDB\Spider\Filter\PreFetchFilterInterface;
use VDB\Uri\UriInterface;

class NegativeUriFilter implements PreFetchFilterInterface
{
    /**
     * @var array An array of regexes
     */
    public $regexes = array();

    public function __construct(array $regexes = array())
    {
        $this->regexes = $regexes;
    }

    public function match(UriInterface $uri)
    {
        foreach ($this->regexes as $regex) {
            if (preg_match($regex, $uri->toString())) {
                return false;
            }
        }
        return true;
    }
}
