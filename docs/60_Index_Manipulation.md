# Lucene Index Manipulation

You can easily manipulate an existing index. 
For example: Deleting instantly a document from index, after it has been removed or updated in pimcore.

For that we're providing a `DocumentModifier` which allows you to:

- mark Lucene-Document as available
- mark Lucene-Document as unavailable
- mark Lucene-Document as deleted (remove from index unrecoverable)

**Note:**: The availability check works within the maintenance cycle!

## Warning!
There are some limitations while changing lucene documents. 
If we change the availability of documents, we can't just update an existing document
since Zend Lucene does not allow us to modify exiting documents. Instead we need to add them as new documents.
Read more about it [here](https://framework.zend.com/manual/1.12/en/zend.search.lucene.index-creation.html#zend.search.lucene.index-creation.document-updating).

### Boost
Because of complex lucene indexing strategies, it's not possible to re-gather the boost factor of documents **and** fields.
So you need to hook into the `lucene_search.modifier.document` event and add those boost values again (see example event below).

### UnStored Fields
Currently it's not possible to re-add fields with type `\Zend_Search_Lucene_Field::unStored` since they are not available in the query document!
If you're changing the availability of documents with `Unstored` fields, they're gone after updating!
Read more about field types [here](https://framework.zend.com/manual/1.10/en/zend.search.lucene.overview.html#zend.search.lucene.index-creation.understanding-field-types).

Solution: Hook into the `lucene_search.modifier.document` event and add them again (see example event below).

## Implementation

```yaml
AppBundle\EventListener\IndexManipulator:
    autowire: true
    tags:
        - { name: kernel.event_subscriber }
```

```php
<?php

namespace AppBundle\EventListener;

use LuceneSearchBundle\LuceneSearchEvents;
use LuceneSearchBundle\Event\DocumentModificationEvent;
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
            LuceneSearchEvents::LUCENE_SEARCH_DOCUMENT_MODIFICATION => 'onModification',
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
    
    /**
    * You only need this method if you want to re-add boost values or unstored fields.
    * 
    * @param DocumentModificationEvent $event
    */
    public function onModification(DocumentModificationEvent $event)
    {
        $document = $event->getDocument();

        $someConditionsAreTrue = false;

        // use this event to re-add boost values
        if ($someConditionsAreTrue === true) {
            $document->boost = 999;
            $event->setDocument($document);
        }
    }
}
```