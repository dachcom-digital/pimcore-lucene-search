# Lucene Index Manipulation

You can easily manipulate an existing index. 
For example: Deleting instantly a document from index, after it has been removed or updated in pimcore.

For that we're providing a `DocumentModifier` which allows you to:

- mark Lucene-Document as available
- mark Lucene-Document as unavailable
- mark Lucene-Document as deleted (remove from index unrecoverable)

```yaml
AppBundle\EventListener\IndexManipulator:
    autowire: true
    tags:
        - { name: kernel.event_subscriber }
```

```php
<?php

namespace AppBundle\EventListener;

use LuceneSearchBundle\Modifier\DocumentModifier;
use Pimcore\Event\DocumentEvents;
use Pimcore\Event\Model\DocumentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class IndexManipulator implements EventSubscriberInterface
{
    protected $documentModifier;

    public function __construct(DocumentModifier $documentModifier)
    {
        $this->documentModifier = $documentModifier;
    }
    
    public static function getSubscribedEvents()
    {
        return [
            DocumentEvents::POST_UPDATE => 'onPostUpdate',
            DocumentEvents::PRE_DELETE  => 'onPreDelete',
        ];
    }

    public function onPostUpdate(DocumentEvent $event)
    {
        $document = $event->getDocument();

        if ($document->isPublished() === true) {
            $marker = DocumentModifier::MARK_AVAILABLE;
        } else {
            $marker = DocumentModifier::MARK_UNAVAILABLE;
        }

        // way 1: use a custom lucene query (slower but could be a complex query)
        // yourCustomMetaIdentifier: you need to add custom Keyword via the lucene_search.task.parser.html_parser event
        $term = new \Zend_Search_Lucene_Index_Term($document->getProperty('yourCustomMetaIdentifierProperty'), 'yourIdentifier');
        $query = new \Zend_Search_Lucene_Search_Query_Term($term);
        $this->documentModifier->markDocumentsViaQuery($query, $marker);

        // way 2: use simple term index (faster but only one term possible)
        // yourCustomMetaIdentifier: you need to add custom Keyword via the lucene_search.task.parser.html_parser event
        $term = new \Zend_Search_Lucene_Index_Term($document->getProperty('yourCustomMetaIdentifierProperty'), 'yourIdentifier');
        $this->documentModifier->markDocumentsViaTerm($term, $marker);

    }

    public function onPreDelete(DocumentEvent $event)
    {
        $document = $event->getDocument();

        // yourCustomMetaIdentifier: you need to add custom Keyword via the lucene_search.task.parser.html_parser event
        $term = new \Zend_Search_Lucene_Index_Term($document->getProperty('yourCustomMetaIdentifierProperty'), 'yourIdentifier');
        $this->documentModifier->markDocumentsViaTerm($term, DocumentModifier::MARK_DELETED);

    }
}
```