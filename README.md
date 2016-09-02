# Pimcore Lucene Search

Just download and install it into your plugin folder.

### Requirements
* Pimcore 4.3

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

### Setup Search Page

- Create a document, call it "search".
- Define a new method in your Controller (eg. search). 
- Create a view template (eg. `content/search.php`) and add this code:

```php
//viewScript = the template file in your website structure.
$this->action('find', 'frontend', 'LuceneSearch', array('viewScript' => 'frontend/find.php')); ?>
```

You'll find the `frontend/find.php` Template in `LuceneSearch/views/scripts/`. If you want to change the markup, just copy the template into your website script folder and change the `viewScript` parameter.

### Using Ajax AutoComplete

Use this snippet to allow ajax driven autocomplete search. you may want to use this [plugin](https://github.com/devbridge/jQuery-Autocomplete) to do the job.

```js
var $el = $('input.search-field'),
    language = $el.data('language'), //optional
    country = $el.data('country'); //optional

$el.autocomplete({
    minChars: 3,
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