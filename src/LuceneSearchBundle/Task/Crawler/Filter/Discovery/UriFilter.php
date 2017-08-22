<?php

namespace LuceneSearchBundle\Task\Crawler\Filter\Discovery;

use LuceneSearchBundle\Task\Crawler\Filter\LogDispatcher;
use VDB\Spider\Filter\PreFetchFilterInterface;
use VDB\Uri\UriInterface;

class UriFilter implements PreFetchFilterInterface
{
    use LogDispatcher;

    /**
     * @var array An array of regexes
     */
    public $regexBag = [];

    /**
     * UriFilter constructor.
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
                $this->notifyDispatcher($uri, 'uri.match.forbidden');
                return TRUE;
            }
        }

        return FALSE;
    }

}
