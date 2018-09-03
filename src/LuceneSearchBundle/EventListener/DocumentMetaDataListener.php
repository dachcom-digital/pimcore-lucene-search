<?php

namespace LuceneSearchBundle\EventListener;

use LuceneSearchBundle\Tool\CrawlerState;
use Pimcore\Http\Request\Resolver\DocumentResolver;
use Pimcore\Model\Document\Page;
use Pimcore\Templating\Helper\HeadMeta;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

class DocumentMetaDataListener {

    /**
     * @var CrawlerState
     */
    protected $crawlerState;

    /**
     * @var DocumentResolver
     */
    protected $documentResolver;

    /**
     * DocumentMetaDataListener constructor.
     *
     * @param StateHandler     $stateHandler
     * @param DocumentResolver $documentResolver
     * @param HeadMeta         $headMeta
     */
    public function __construct(CrawlerState $crawlerState, DocumentResolver $documentResolver, HeadMeta $headMeta)
    {
        $this->crawlerState = $crawlerState;
        $this->documentResolver = $documentResolver;
        $this->headMeta = $headMeta;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        if (!$this->crawlerState->isLuceneSearchCrawler()) {
            return;
        }

        $request = $event->getRequest();

        if (!$event->isMasterRequest()) {
            return;
        }

        $document = $this->documentResolver->getDocument($request);

        if ($document instanceof Page) {
            $this->headMeta->addRaw('<meta name="lucene-search:documentId" content="' . $document->getId() . '" />');
        }
    }

}