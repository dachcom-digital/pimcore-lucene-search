<?php

namespace LuceneSearchBundle\Event;

use Symfony\Component\EventDispatcher\Event;

class HtmlParserEvent extends Event
{

    /**
     * @var \Zend_Search_Lucene_Document
     */
    private $document;

    /**
     * @var string
     */
    private $html;

    /**
     * @var array
     */
    private $params;

    public function __construct(\Zend_Search_Lucene_Document $document, $html, $params)
    {
        $this->document = $document;
        $this->html = $html;
        $this->params = $params;
    }

    /**
     * @return \Zend_Search_Lucene_Document
     */
    public function getDocument()
    {
        return $this->document;
    }

    /**
     * @return string
     */
    public function getHtml()
    {
        return $this->html;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

}