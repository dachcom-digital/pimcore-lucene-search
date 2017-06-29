# Custom Meta Content

In some cases you need to add some content or keywords to improve the search accuracy. 
But it's not meant for the public crawlers like Google. LuceneSearch is using a custom meta property called `lucene-search:meta`.
This Element should be visible while crawling only.

**Example:**

```html
{% if lucene_search_crawler_active() %}
    <meta name="lucene-search:meta" content="meta data for lucene search">
{% endif %}
```

## Custom Meta in Documents
It's also possible to add the custom meta property in backend.
 
Open *Document* => *Settings* go to *Meta Data* and add a new field:

```html
<meta name="lucene-search:meta" content="your content">
```

> **Note:** Currently it's not possible to hide this meta tag if you're adding it via backend since pimcore provides no way to add/remove/modify those elements programmatically.

## Custom Meta in Objects
Because Object may have some front-end capability (a news detail page for example), you have to integrate the custom meta field by yourself (see example above).

## Custom Meta in Assets
TBD