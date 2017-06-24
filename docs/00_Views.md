# Lucene Search FrontEnd

This guide will help you to implement a search page into your website in seconds.

### Setup Search Page
- Create a document, call it "search".
- Define a new method in your Controller (eg. search). 
- Create a view template (eg. `content/search.php`) and add this code:

```php
//viewScript = the template file in your website structure.
$this->action('find', 'frontend', 'LuceneSearch', array('viewScript' => 'frontend/find.php'));
```

You'll find the `frontend/find.php` Template in `LuceneSearch/views/scripts/`. If you want to change the markup, just copy the template into your website script folder and change the `viewScript` parameter.

## Using Ajax AutoComplete
Use this snippet to allow ajax driven autocomplete search. you may want to use this [plugin](https://github.com/devbridge/jQuery-Autocomplete) to do the job.

Add some JS files:

```html
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.devbridge-autocomplete/1.4.1/jquery.autocomplete.min.js"></script>
```

Add this to your project:

```javascript
$(function() {

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

});
```