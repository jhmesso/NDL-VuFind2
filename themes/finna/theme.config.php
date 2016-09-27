<?php
return array(
    'extends' => 'bootstrap3',
    'helpers' => array(
        'factories' => array(
            'authorizationNote' => 'Finna\View\Helper\Root\Factory::getAuthorizationNote',
            'autocomplete' => 'Finna\View\Helper\Root\Factory::getAutocomplete',
            'browse' => 'Finna\View\Helper\Root\Factory::getBrowse',
            'callnumber' => 'Finna\View\Helper\Root\Factory::getCallnumber',
            'combined' => 'Finna\View\Helper\Root\Factory::getCombined',
            'content' => 'Finna\View\Helper\Root\Factory::getContent',
            'feed' => 'Finna\View\Helper\Root\Factory::getFeed',
            'fileSrc' => 'Finna\View\Helper\Root\Factory::getFileSrc',
            'header' => 'Finna\View\Helper\Root\Factory::getHeader',
	    'headLink' => 'FinnaTheme\View\Helper\Factory::getHeadLink',
            'headScript' => 'FinnaTheme\View\Helper\Factory::getHeadScript',
            'headTitle' => 'Finna\View\Helper\Root\Factory::getHeadTitle',
            'holdingsSettings' => 'Finna\View\Helper\Root\Factory::getHoldingsSettings',
            'imageSrc' => 'Finna\View\Helper\Root\Factory::getImageSrc',
            'indexedTotal' => 'Finna\View\Helper\Root\Factory::getTotalIndexed',
            'layoutclass' => 'Finna\View\Helper\Root\Factory::getLayoutClass',
            'metalib' => 'Finna\View\Helper\Root\Factory::getMetaLib',
            'navibar' => 'Finna\View\Helper\Root\Factory::getNavibar',
            'onlinePayment' => 'Finna\View\Helper\Root\Factory::getOnlinePayment',
            'openUrl' => 'Finna\View\Helper\Root\Factory::getOpenUrl',
            'organisationInfo' => 'Finna\View\Helper\Root\Factory::getOrganisationInfo',
            'organisationsList'
                => 'Finna\View\Helper\Root\Factory::getOrganisationsList',
            'personaAuth' => 'Finna\View\Helper\Root\Factory::getPersonaAuth',
            'piwik' => 'Finna\View\Helper\Root\Factory::getPiwik',
            'primo' => 'Finna\View\Helper\Root\Factory::getPrimo',
            'record' => 'Finna\View\Helper\Root\Factory::getRecord',
            'recordImage' => 'Finna\View\Helper\Root\Factory::getRecordImage',
            'recordLink' => 'Finna\View\Helper\Root\Factory::getRecordLink',
            'scriptSrc' => 'Finna\View\Helper\Root\Factory::getScriptSrc',
            'searchbox' => 'Finna\View\Helper\Root\Factory::getSearchBox',
            'searchTabs' => 'Finna\View\Helper\Root\Factory::getSearchTabs',
            'searchTabsRecommendations' => 'Finna\View\Helper\Root\Factory::getSearchTabsRecommendations',
            'streetSearch' => 'Finna\View\Helper\Root\Factory::getStreetSearch',
            'systemMessages' => 'Finna\View\Helper\Root\Factory::getSystemMessages',
            'translation' => 'Finna\View\Helper\Root\Factory::getTranslation',
            'proxyurl' => 'Finna\View\Helper\Root\Factory::getProxyUrl',
        ),
        'invokables' => array(
	    'body' => 'Finna\View\Helper\Root\Body',
            'checkboxFacetCounts' =>
                'Finna\View\Helper\Root\CheckboxFacetCounts',
            'resultfeed' => 'Finna\View\Helper\Root\ResultFeed',
            'search' => 'Finna\View\Helper\Root\Search',
            'translationEmpty' => 'Finna\View\Helper\Root\TranslationEmpty',
            'truncateUrl' => 'Finna\View\Helper\Root\TruncateUrl',
            'userPublicName' => 'Finna\View\Helper\Root\UserPublicName',
        )
    ),
    'css' => array(
        'vendor/dataTables.bootstrap.min.css',
        'vendor/magnific-popup.min.css',
        'dataTables.bootstrap.custom.css',
        'vendor/slick.min.css',
        'vendor/bootstrap-multiselect.min.css',
        'vendor/bootstrap-datepicker3.min.css',
        'finna.css'
    ),
    'js' => array(
        'vendor/event-stub.js:lt IE 9',
        'finna.js',
        'finna-autocomplete.js',
        'finna-combined-results.js',
        'image-popup.js',
        'finna-adv-search.js',
        'finna-daterange-vis.js',
        'finna-feed.js',
        'finna-layout.js',
        'finna-openurl.js',
        'finna-persona.js',
        'finna-common.js',
        'vendor/jquery.dataTables.min.js',
        'vendor/dataTables.bootstrap.min.js',
        'vendor/jquery.inview.min.js',
        'vendor/jquery.magnific-popup.min.js',
        'vendor/jquery.cookie-1.4.1.min.js',
        'vendor/slick.min.js',
        'vendor/jquery.touchSwipe.min.js',
        'vendor/bootstrap-multiselect.min.js',
        'vendor/gauge.min.js'
    ),
    'less' => array(
        'active' => false
    ),
    'favicon' => 'favicon.ico',
);
