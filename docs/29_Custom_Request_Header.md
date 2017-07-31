# Custom Request Header

Add some header information to the crawler request.

> The [Members](https://github.com/dachcom-digital/pimcore-members) Bundle adds a auth header element by default.

## Event

| Name | Class | Setter |
|---------------------|-------------|-------------------------------|
| `lucene_search.task.crawler.request_header` | Event\CrawlerRequestHeaderEvent | addHeader |

## Example: Auth

```php
<?php

namespace AppBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use LuceneSearchBundle\Event\CrawlerRequestHeaderEvent;

class CrawlerHeader implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            'lucene_search.task.crawler.request_header' => 'addHeaderToLuceneCrawler'
        ];
    }

    public function addHeaderToLuceneCrawler(CrawlerRequestHeaderEvent $event)
    {
        //example 1: token auth.
        $event->addHeader([
            'name'          => 'x-auth-token',
            'value'         => 'your-special-token',
            'identifier'    => 'lucene-search-token-auth'
        ]);
        
        //example 2: basic auth.
        $event->addHeader([
            'name'          => 'Authorization',
            'value'         => 'Basic ' . base64_encode('USERNAME:PASSWORD'),
            'identifier'    => 'lucene-search-basic-auth'
        ]);
    }
}
```