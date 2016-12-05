# Pimcore Lucene Search
Pimcore 4.0 Website Search (powered by Zend Search Lucene)

### Requirements
* Pimcore 4.3

## Installation
**Handcrafted Installation**   
1. Download Plugin  
2. Rename it to `LuceneSearch`  
3. Place it in your plugin directory  
4. Activate & install it through backend 

**Composer Installation**  
1. Add code below to your `composer.json`    
2. Activate & install it through backend

```json
"require" : {
    "dachcom-digital/pimcore-lucene-search" : "1.1.1",
}
```

### Features
* Maintenance driven indexing
* Auto Complete
* Restricted Documents & Usergroups ([member](https://github.com/dachcom-digital/pimcore-members) plugin recommended but not required)
* Authenticated Crawling

### Document Restrictions
If you want a seamless integration of protected document crawling, install our [member](https://github.com/dachcom-digital/pimcore-members) plugin.

#### How does the document restriction work?
Each document needs a meta tag in the head section. the crawler extract and stores the usergroup id(s) from that meta property. 
To allow the crawler to follow all the restricted documents, you need to configure the crawler authentication settings. 

**Meta Property Example**

```html
<meta name="m:groups" content="4">
```

If the document is restricted to a specific usergroup, the meta `content` contains its id. Otherwise, the meta property needs to be filled with a `default` value.

## Asset Language restriction
Because Assets does not have any language hierarchy, you need to add a property called `assigned_language`. This Property will be installed during the install process of LuceneSearch.
If you add some additional language afterwards, you need to add this language to the property. if you do not set any information at all, the asset will be found in any language context.

## Setup Search Page
- Create a document, call it "search".
- Define a new method in your Controller (eg. search). 
- Create a view template (eg. `content/search.php`) and add this code:

```php
//viewScript = the template file in your website structure.
$this->action('find', 'frontend', 'LuceneSearch', array('viewScript' => 'frontend/find.php')); ?>
```

You'll find the `frontend/find.php` Template in `LuceneSearch/views/scripts/`. If you want to change the markup, just copy the template into your website script folder and change the `viewScript` parameter.

## Using Ajax AutoComplete
Use this snippet to allow ajax driven autocomplete search. you may want to use this [plugin](https://github.com/devbridge/jQuery-Autocomplete) to do the job.

```js
var $el = $('input.search-field'),
    language = $el.data('language'), //optional
    country = $el.data('country'); //optional

$el.autocomplete({
    minChars: 3,
    triggerSelectOnValidInput: false,
    lookup: function(term, done) {

        $.getJSON(
            '/plugin/LuceneSearch/frontend/auto-complete/',
            {
                q: term,
                language : language,
                country: country
            },
            function(data) {

                var result = { suggestions : [] };

                if(data.length > 0) {
                    $.each(data, function(index, suggession) {
                        result.suggestions.push( {value : suggession });
                    });
                }

                done(result);

        });

    },
    onSelect: function(result) {

        $el.val(result.value);
        $el.parents('form').submit();

    }

});
```

## Custom Meta Content
In some cases you need to add some content or keywords to improve the search accuracy. 
But it's not meant for the public crawlers like Google. LuceneSearch uses a custom meta property called `lucene-search:meta`.
This Element should be visible while crawling only.

**Custom Meta in Documents**  
In *Document* => *Settings* go to *Meta Data* and add a new field:

```config
[
    meta    => name,
    name    => "lucene-search:meta"
    content => "your content"
]
```

**Custom Meta in Objects**  
Because Object may have some front-end capability (a news detail page for example), you have to integrate the custom meta field by yourself.

**Example:**

```php
if( \LuceneSearch\Tool\Request::isLuceneSearchCrawler() )
{
    $this->view->headMeta()->setName( 'lucene-search:meta', $product->getInternalSearchText( $lang ) );
}
```

**Custom Meta in Assets**  
TBD

## Upgrade Info
Before updating, please [check our upgrade notes!](UPGRADE.md)