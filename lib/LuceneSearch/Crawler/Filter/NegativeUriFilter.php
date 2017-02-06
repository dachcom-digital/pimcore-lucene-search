<?php
namespace LuceneSearch\Crawler\Filter;

use VDB\Spider\Filter\PreFetchFilterInterface;
use VDB\Uri\UriInterface;

class NegativeUriFilter implements PreFetchFilterInterface
{
    /**
     * @var array An array of regexes
     */
    public $regexes = [];

    /**
     * NegativeUriFilter constructor.
     *
     * @param array $regexes
     */
    public function __construct(array $regexes = [])
    {
        $this->regexes = $regexes;
    }

    /**
     * @param UriInterface $uri
     *
     * @return bool
     */
    public function match(UriInterface $uri)
    {
        foreach ($this->regexes as $regex) {
            if (preg_match($regex, $uri->toString())) {
                return FALSE;
            }
        }

        return TRUE;
    }
}
