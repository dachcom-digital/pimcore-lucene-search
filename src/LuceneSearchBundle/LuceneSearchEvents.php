<?php

namespace LuceneSearchBundle;

final class LuceneSearchEvents
{
    /**
     * Triggers before a request starts.
     * Use it to add some custom header information like authentication for example
     */
    const LUCENE_SEARCH_CRAWLER_REQUEST_HEADER = 'lucene_search.task.crawler.request_header';

    /**
     * Triggers before a pdf document gets added to lucene index.
     * Use it to add custom data to the lucene document.
     */
    const LUCENE_SEARCH_PARSER_PDF_DOCUMENT = 'lucene_search.task.parser.pdf_parser';

    /**
     * Triggers before a html document gets added to lucene index.
     * Use it to add custom data to the lucene document.
     */
    const LUCENE_SEARCH_PARSER_HTML_DOCUMENT = 'lucene_search.task.parser.html_parser';

    /**
     * Triggers while asset meta data gets requested.
     * Use it if you have some special restriction information for the given asset.
     * If you're using the dachcom-digital/members extension, this event will be used.
     */
    const LUCENE_SEARCH_PARSER_ASSET_RESTRICTION  = 'lucene_search.task.parser.asset_restriction';

    /**
     * Triggers in every frontend lucene search query
     * If you're using the dachcom-digital/members extension, this event will be used.
     */
    const LUCENE_SEARCH_FRONTEND_RESTRICTION_CONTEXT  = 'lucene_search.frontend.restriction_context';

    /**
     * @internal
     *
     * Triggers if the crawl task stops via command line cancelling signal
     * This is an spider internal event and cannot be used outside the crawler dispatcher
     */
    const LUCENE_SEARCH_CRAWLER_INTERRUPTED = 'lucene_search.crawl.interrupted';
}
