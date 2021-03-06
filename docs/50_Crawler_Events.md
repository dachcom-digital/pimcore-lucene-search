# Crawler Events

Hook into crawler process to add custom fields to current lucene document.

## HtmlParserEvent params

### Document Id
The crawler will always add the ID of the current indexed pimcore document to the params array.
You can access it using `$params['document_id']`.

> **Note!** The document id is not available in the lucene index unless you're adding it via the parser event (see example below)

### Object Id
The crawler will check for the presence of a meta tag called `lucene-search:objectId`.
If the meta tag is present, the objectId will be passed to the event inside the params array. 
You can access it using `$params['object_id']`.

Since it is not possible to automatically detect the current object id, you need to add it by yourself.
This is an example how you could implement the *lucene-search:objectId* meta tag:

```html
{% if lucene_search_crawler_active() %}
    {% do pimcore_head_meta().appendName('lucene-search:objectId', product.id) %}
{% endif %}
```

## Configuration

```yaml
    AppBundle\EventListener\LuceneSearchParserListener:
        autowire: true
        tags:
            - { name: kernel.event_subscriber }
``` 

## Services

```php
<?php

namespace AppBundle\EventListener;

use Pimcore\Model\DataObject;
use LuceneSearchBundle\Event\HtmlParserEvent;
use LuceneSearchBundle\Event\PdfParserEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use LuceneSearchBundle\LuceneSearchEvents;

class LuceneSearchParserListener implements EventSubscriberInterface {

    public static function getSubscribedEvents()
    {
        return [
            LuceneSearchEvents::LUCENE_SEARCH_PARSER_HTML_DOCUMENT => 'parseHtml',
            LuceneSearchEvents::LUCENE_SEARCH_PARSER_PDF_DOCUMENT => 'parsePdf',
        ];
    }

    public function parseHtml(HtmlParserEvent $event)
    {
        $luceneDoc = $event->getDocument();
        $html = $event->getHtml();
        $params = $event->getParams();
        
        if (!empty($params['document_id'])) {
            $document = \Pimcore\Model\Document::getById($params['document_id']);
            $documentIdField = \Zend_Search_Lucene_Field::keyword('documentId', $document->getId());
            $luceneDoc->addField($documentIdField);
        }
        
        if (!empty($params['object_id'])) {
            $object = DataObject::getById($params['object_id']);
            $objectIdField = \Zend_Search_Lucene_Field::keyword('objectId', $object->getId());
            $luceneDoc->addField($objectIdField);
        }

        // additional fields
        $field = \Zend_Search_Lucene_Field::text('myCustomField', 'Custom field content', $params['encoding']);
        $field->boost = 5;
        
        $luceneDoc->addField($field);
        
        $event->setDocument($luceneDoc);
    }
    
    public function parsePdf(PdfParserEvent $event) 
    {
        $luceneDoc = $event->getDocument();
        $content = $event->getContent();
        $assetMetaData = $event->getAssetMetaData();
        $params = $event->getParams();
        
        $field = \Zend_Search_Lucene_Field::text('myCustomField', 'Custom field content', $params['encoding']);
        $luceneDoc->addField($field);
        
        $event->setDocument($luceneDoc);
    }
}
```
