# Lucene Index Manipulation

You can easily manipulate an existing index. 
For example: Deleting instantly a document from index, after it has been removed or updated in pimcore.

```yaml
AppBundle\EventListener\IndexManipulator:
    autowire: true
    tags:
        - { name: kernel.event_subscriber }
```

```php
<?php

namespace AppBundle\EventListener;

use LuceneSearchBundle\Configuration\Configuration;
use Pimcore\Event\DocumentEvents;
use Pimcore\Event\Model\DocumentEvent;
use Pimcore\Model\Document;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class IndexManipulator implements EventSubscriberInterface
{
    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            DocumentEvents::POST_UPDATE => 'onPostUpdate',
            DocumentEvents::PRE_DELETE  => 'onPreDelete',
        ];
    }

    /**
     * @param DocumentEvent $event
     */
    public function onPostUpdate(DocumentEvent $event)
    {
        $document = $event->getDocument();

        $somethingWrong = false; // your logic here.
        
        if($somethingWrong === true) {
            $this->deleteDocumentFromLuceneDatabase($document);
        }
    }

    /**
     * @param DocumentEvent $event
     */
    public function onPreDelete(DocumentEvent $event)
    {
        $document = $event->getDocument();
        $this->deleteDocumentFromLuceneDatabase($document);

    }

    /**
     * @param Document $document
     */
    private function deleteDocumentFromLuceneDatabase(Document $document)
    {
        $frontendIndex = \Zend_Search_Lucene::open(Configuration::INDEX_DIR_PATH_STABLE);
        
        // yourCustomMetaIdentifier: in this example we're using the lucene-search:meta meta tag
        $term = new \Zend_Search_Lucene_Index_Term('yourCustomMetaIdentifier', 'customMeta');
        $query = new \Zend_Search_Lucene_Search_Query_Term($term);

        try {
            $hits = $frontendIndex->find($query);
        } catch (\Exception $e) {
            return;
        }

        if (!is_array($hits) || count($hits) === 0) {
            return;
        }

        foreach ($hits as $hit) {

            if (!$hit instanceof \Zend_Search_Lucene_Search_QueryHit) {
                continue;
            }

            try {
                $frontendIndex->delete($hit);
                $frontendIndex->optimize();
            } catch (\Zend_Search_Lucene_Exception $e) {
                // fail silently
            }
        }
    }
}
```