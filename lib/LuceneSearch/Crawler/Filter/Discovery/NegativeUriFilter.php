<?php

namespace LuceneSearch\Crawler\Filter\Discovery;

use LuceneSearch\Crawler\Filter\LogDispatcher;
use VDB\Spider\Filter\PreFetchFilterInterface;
use VDB\Uri\UriInterface;

class NegativeUriFilter implements PreFetchFilterInterface
{
    use LogDispatcher;

    /**
     * @var array An array of regexes
     */
    public $regexBag = [];

    /**
     * NegativeUriFilter constructor.
     *
     * @param array                                              $regexBag
     * @param \Symfony\Component\EventDispatcher\EventDispatcher $dispatcher
     */
    public function __construct(array $regexBag = [], $dispatcher)
    {
        $this->regexBag = $regexBag;
        $this->setDispatcher($dispatcher);
    }

    /**
     * @param UriInterface $uri
     *
     * @return bool
     */
    public function match(UriInterface $uri)
    {
        foreach ($this->regexBag as $regex) {
            if (preg_match($regex, $uri->toString())) {
                return FALSE;
            }
        }

        $this->notifyDispatcher($uri, 'uri.match.invalid');

        return TRUE;
    }
}
