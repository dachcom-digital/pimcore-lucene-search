# Crawler Events

Hook into crawler process to add custom fields to current lucene document


```yaml
    AppBundle\EventListener\LuceneSearchParserListener:
        autowire: true
        tags:
            - { name: kernel.event_subscriber }
``` 

```php
<?php

namespace AppBundle\EventListener;

use CoreShop\Component\Pimcore\Routing\LinkGeneratorInterface;
use LuceneSearchBundle\Event\HtmlParserEvent;
use LuceneSearchBundle\Event\PdfParserEvent;
use Pimcore\Model\DataObject;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @package AppBundle\EventListener
 */
class LuceneSearchParserListener implements EventSubscriberInterface {

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'lucene_search.task.parser.html_parser' => 'parseHtml',
            'lucene_search.task.parser.pdf_parser' => 'parsePdf',
        ];
    }

    public function parseHtml(HtmlParserEvent $event)
    {
        $luceneDoc = $event->getDocument();
        $html = $event->getHtml();
        $params = $event->getParams();
        
        if (!empty($params['document_id'])) {
            $document = \Pimcore\Model\Document::getById($params['document_id']);
        }
        
        if (!empty($params['object_id'])) {
            $object = DataObject::getById($params['object_id']);
        }

        $field = \Zend_Search_Lucene_Field::Text('myCustomField', 'Custom field content', $params['encoding']);
        $field->boost = 5;
        $luceneDoc->addField($field);
    }
    
    public function parsePdf(PdfParserEvent $event) 
    {
        $luceneDoc = $event->getDocument();
        $content = $event->getContent();
        $assetMetaData = $event->getAssetMetaData();
        $params = $event->getParams();
        
        $field = \Zend_Search_Lucene_Field::Text('myCustomField', 'Custom field content', $params['encoding']);
        $luceneDoc->addField($field);
    }
```

**HtmlParserEvent params**

The crawler will allways add the ID of the current indexed pimcore document to the params array. You can access it using `$params['document_id']`

The crawler will check for the presence of a meta tag called *lucene-search:objectId*. If the meta tag is present, the objectId will be passed to the event inside the params array. You can access it using `$params['object_id']`

This is an example how you could imlement the *lucene-search:objectId* meta tag:

```html
{% if lucene_search_crawler_active() %}
    {% do pimcore_head_meta().appendName('lucene-search:objectId', product.id) %}
{% endif %}
```