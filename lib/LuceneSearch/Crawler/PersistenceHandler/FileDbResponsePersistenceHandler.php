<?php

namespace LuceneSearch\Crawler\PersistenceHandler;

use VDB\Spider\PersistenceHandler\MemoryPersistenceHandler;
use VDB\Spider\PersistenceHandler\PersistenceHandlerInterface;
use VDB\Spider\Resource;

class FileDbResponsePersistenceHandler extends MemoryPersistenceHandler implements PersistenceHandlerInterface
{
    /**
     * @var Resource[]
     */
    private $resourceIds = [];

    /**
     * @var \Pimcore\Db
     */
    var $db = NULL;

    /**
     * FileDbResponsePersistenceHandler constructor.
     */
    public function __construct()
    {
        $this->db = \Pimcore\Db::get();
    }

    /**
     * @param string $spiderId
     */
    public function setSpiderId($spiderId)
    {
        // db handler ignores this. Only interesting for file persistence as some kind of key or prefix
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->resourceIds);
    }

    /**
     * @param Resource $resource
     */
    public function persist(Resource $resource)
    {
        $identifier = md5($resource->getUri()->toString());
        $response = $resource->getResponse();
        $host = $resource->getUri()->getHost();
        $uri = $resource->getUri()->toString();

        $contentTypeStr = NULL;
        $contentType = $response->getHeader('Content-Type');
        if (!is_null($contentType)) {
            $contentTypeStr = $contentType->__toString();
        }

        $contentLanguageStr = NULL;
        $contentLanguage = $response->getHeader('Content-Language');
        if (!is_null($contentLanguage)) {
            $contentLanguageStr = $contentLanguage->__toString();
        }

        $rawResponse = $resource->getResponse()->getBody(TRUE);

        $insert = 'INSERT INTO lucene_search_index (identifier, contentType, contentLanguage, host, uri, content) VALUES (?, ?, ?, ?, ?, ?)';

        $this->db->query($insert, [$identifier, $contentTypeStr, $contentLanguageStr, $host, $uri, $rawResponse]);

        $this->resourceIds[] = $identifier;
    }

    /**
     * @return Resource
     */
    public function current()
    {
        $identifier = current($this->resourceIds);
        $data = $this->db->fetchRow('SELECT * FROM lucene_search_index WHERE identifier = ?', $identifier);

        if (is_array($data)) {
            $crawler = new \Symfony\Component\DomCrawler\Crawler('', $data['uri']);
            $crawler->addContent(
                $data['content'],
                $data['contentType']
            );

            $data['crawler'] = $crawler;
        }

        return $data;
    }

    /**
     * @return Resource|false
     */
    public function next()
    {
        next($this->resourceIds);
    }

    /**
     * @return int
     */
    public function key()
    {
        return key($this->resourceIds);
    }

    /**
     * @return boolean
     */
    public function valid()
    {
        return (bool)current($this->resourceIds);
    }

    /**
     * @return void
     */
    public function rewind()
    {
        reset($this->resourceIds);
    }

}
