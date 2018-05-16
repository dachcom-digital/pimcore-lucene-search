<?php

namespace LuceneSearchBundle\Event;

use Symfony\Component\EventDispatcher\Event;

class PdfParserEvent extends Event {

    /**
     * @var \Zend_Search_Lucene_Document
     */
    private $document;

    /**
     * @var string
     */
    private $content;

    /**
     * @var array
     */
    private $assetMetaData;

    /**
     * @var array
     */
    private $params;

    public function __construct(\Zend_Search_Lucene_Document $document, $content, $assetMetaData, $params)
    {
        $this->document = $document;
        $this->content = $content;
        $this->assetMetaData = $assetMetaData;
        $this->params = $params;
    }

    /**
     * @return \Zend_Search_Lucene_Document
     */
    public function getDocument() {
        return $this->document;
    }

    /**
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    public function getAssetMetaData()
    {
        return $this->assetMetaData;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

}