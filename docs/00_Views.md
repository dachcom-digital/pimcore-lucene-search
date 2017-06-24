# Lucene Search FrontEnd

This guide will help you to implement a search page into your website in seconds.

### Setup Search Page
- Create a document, call it "search"
- Create a view template (eg. `AppBundle\Resource\views\Search\sarch.html.twig`)
- Add this code to the view:

```twig
{{ render(controller('lucene_search.controller.frontend.list:getResultAction')) }}
```

This will load the result template from `@LuceneSearch/Resources/views/List/result.html.twig`.

### Ajax AutoComplete
Use this snippet to allow ajax driven auto-complete search. you may want to use this [plugin](https://github.com/devbridge/jQuery-Autocomplete) to do the job.

1. Add some JS files:

```html
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.devbridge-autocomplete/1.4.1/jquery.autocomplete.min.js"></script>
```

2. Add auto-complete to your project:

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
                '/lucence-search/auto-complete',
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

3. Place this html snippet in your header for example:

```html
<form id="search" method="get" action="/en/search">
    <div class="input-group">
        <input type="text" name="q" class="form-control input-lg search-field" data-language="en" placeholder="{{ 'search'|trans }}">
        <input type="hidden" name="language" id="searchLanguage" value="en">
        <span class="input-group-btn">
            <button class="btn btn-lg" type="button">
                <i class="fa fa-search"></i>
            </button>
        </span>
    </div>
</form>
```

4. Done. Now try to search something without hitting return.

> Don't forget to start your crawler before testing the autocompleter.