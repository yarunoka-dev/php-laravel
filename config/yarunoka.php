<?php

// Configuration for the Laravel bridge of Yarunoka (the Yrnk schedule DSL)
return [

    // The timezone schedules are judged in. null falls back to
    // config('app.timezone'). An app that runs app.timezone at UTC should
    // name its wall-clock timezone here (e.g. 'Asia/Tokyo')
    'timezone' => null,

    // The calendar part of a Yrnk document, spelled as a PHP array (the
    // same shape the DSL accepts under "calendar"). A date-list position
    // (holidays / business_holidays / business_days) takes either the
    // date list itself or the name of what resolves it:
    //   'holidays' => ['2026-01-01', ...]
    //   'holidays' => 'company-holidays'  (a name from 'resolvers' below)
    //   'holidays' => 'yasumi-Japan'      (bound automatically when
    //                                      azuyalabs/yasumi is installed)
    // Omitting a key leaves that definition undefined (vocabulary that
    // requires it is then a validation error)
    'calendar' => [],

    // Resolver name => class-string of a YrnkResolverInterface
    // implementation. Instances are made by the Laravel container, so
    // constructor injection works. The names are usable wherever the DSL
    // accepts a name: the calendar positions above, and stored documents
    'resolvers' => [],

];
