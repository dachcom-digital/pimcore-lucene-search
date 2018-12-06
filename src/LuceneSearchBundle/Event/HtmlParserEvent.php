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
    private $parsedHtml;

    /**
     * @var string
     */
    private $fullHtml;

    /**
     * @var array
     */
    private $params;

    /**
     * HtmlParserEvent constructor.
     *
     * @param \Zend_Search_Lucene_Document $document
     * @param                              $parsedHtml
     * @param                              $fullHtml
     * @param                              $params
     */
    public function __construct(\Zend_Search_Lucene_Document $document, $parsedHtml, $fullHtml, $params)
    {
        $this->document = $document;
        $this->parsedHtml = $parsedHtml;
        $this->fullHtml = $fullHtml;
        $this->params = $params;
    }

    /**
     * @param \Zend_Search_Lucene_Document $document
     *
     * @return \Zend_Search_Lucene_Document
     */
    public function setDocument(\Zend_Search_Lucene_Document $document)
    {
        return $this->document = $document;
    }

    /**
     * @return \Zend_Search_Lucene_Document
     */
    public function getDocument()
    {
        return $this->document;
    }

    /**
     * @deprecated Use getParsedHtml() instead.
     *
     * @return string
     */
    public function getHtml()
    {
        return $this->getParsedHtml();
    }

    /**
     * @return string
     */
    public function getParsedHtml()
    {
        return $this->html;
    }

    /**
     * @return string
     */
    public function getFullHtml()
    {
        return $this->fullHtml;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }
}